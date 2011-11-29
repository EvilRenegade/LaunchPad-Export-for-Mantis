<?php
/*
    LaunchPad Export for Mantis exports the selected bugs into a LaunchPad-schema
    conform XML file.
    Copyright (C) 2011  Charly "Renegade" Kiendl

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
	
*/
require_once(dirname(dirname(__FILE__))."/lpxmlwriter.php"); //open_basedir_restriction didn't appreciate the prettier version of ".."
require_once(dirname(dirname(__FILE__))."/stopwords.php");
assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_BAIL, 1);
assert_options(ASSERT_CALLBACK, 'panic');

function panic($file, $line, $expr) {
	echo "An assertion in $file of LaunchPad Export for Mantis failed: Line $line, assertion $expr was not true.";
}

class bugWrapper {
	private $bug = NULL; //!< Reference to the bug we're wrapping around.
	private $xml = NULL; //!< Reference to the lpXmlWriter handling the output.
	private $relationships = NULL; //!< Contains this bug's relationships. \sa getRelationships()
	private $hasAttachments; //!< Whether this bug has attachments. Set in __construct().
	private $commentCount; //!< How many comments this bug has. Set in __construct().
	private $id; //!< This bug's ID.
	private $reporter; //!< This bug's reporter. NULL or person array.
	private $description; //!< This bug's full description.
	public static $bugnoteTable; //!< The name of the bugnotes table in the DB.
	public static $bugfileTable; //!< The name of the bug files table in the DB.
	public static $usedNicknames = array(); //!< Static array of bug nicknames already used.
	
	/**	\brief Returns this bug's ID.
		\return This bug's ID.
	*/
	private function getId() {
		if(!isset($this->id)) {
			$this->id = $this->bug->__get('id');
		}
		return $this->id;
	}
	
	/** \brief Helper function checking whether the given user is a system account, rather than an actual user.
		\details Specifically, it checks whether the given user is the anonymous or the source control account.
		\param $pUserId The ID of the user to check.
		\return True if the given user is not a real user, false if he is a real user.
	*/
	private function notARealUser($pUserId) {
		if(!user_exists($pUserId)) {
			return true; // Mantis is pesky.
		}
		return user_is_anonymous($pUserId) || (user_get_id_by_name(config_get('source_control_account')) == $pUserId);
	}
	
	/**	\brief Returns the author/submitter/reporter of this bug.
		\return NULL if the reporter is not a real user (e.g. anonymous system account), a person array if he is.
	*/
	private function getReporter() {
		if(!isset($this->reporter)) {			
			$repId = $this->bug->__get('reporter_id');
			if($this->notARealUser($repId)) {
				$this->reporter = NULL; // could potentially lead to double- or triple-execution, but it's the least ugly way, imo
			} else {
				$repName = user_get_name($repId);
				$this->reporter = lpXmlWriter::gpa($repName, user_get_email($repId), $repName);
			}
		}
		return $this->reporter;
	}
	
	/**	\brief Helper function to cache the output of getFullDescription(). Use this rather than that.
		\sa getFullDescription()
		\return The full description of this bug.
	*/
	private function getDescription() {
		if(!isset($this->description)) {
			$this->description = $this->getFullDescription();
		}
		return $this->description;
	}
	
	/** \brief Whether this bug is private.
		\return Boolean, whether this bug is private.
	*/
	private function isPrivate() {
		return ($this->bug->__get('view_state') == VS_PRIVATE);
	}
	
	/**	\brief Returns the relationships this bug is the source of.
		\return An array of BugRelationshipData objects, the relationships this bug is the source of.
	*/
	private function getRelationships() {
		if(is_null($this->relationships)) {
			$this->relationships = relationship_get_all_src($this->getId());
		}
		
		return $this->relationships;
	}
	
	/**	\brief If this bug is a duplicate of another bug, this function gets the bug ID of the original.
		\return The bug ID of the bug this bug is a duplicate of, or 0 if this is not a duplicate.
	*/
	private function getOriginal() {
		$rships = $this->getRelationships();
		
		foreach($rships as $relationship) {
			if($relationship->type == BUG_DUPLICATE) {
				return intval($relationship->dest_bug_id);
			}
		}
		
		return 0;
	}

	/**	\brief Generates tags out of the relationships a bug is in.
		\return An array of tags (strings).
	*/
	private function getRelationshipTags() {
		$rships = $this->getRelationships();
		$tags = array();
		
		foreach($rships as $relationship) {
			switch($relationship->type) {
				/*case BUG_DUPLICATE: // for debugging/crosschecking purposes: Is taken care of through <duplicateof>-tag (see above)
					array_push($tags, "relationship-duplicate");
					array_push($tags, "relationship-duplicate-of+".$relationship->dest_bug_id);
					break;*/
				case BUG_HAS_DUPLICATE:
					array_push($tags, "relationship-duplicate");
					array_push($tags, "relationship-has-duplicate+".$relationship->dest_bug_id);
					break;
				case BUG_RELATED:
					array_push($tags, "relationship-related");
					array_push($tags, "relationship-is-related-to+".$relationship->dest_bug_id);
					break;
				case BUG_DEPENDANT: // parent of
					array_push($tags, "relationship-dependency");
					array_push($tags, "relationship-depends-on+".$relationship->dest_bug_id);
					break;
				case BUG_BLOCKS: // child of
					array_push($tags, "relationship-dependency");
					array_push($tags, "relationship-blocks+".$relationship->dest_bug_id);
					break;
				
				/*
					Custom relationships follow; if you have your own custom
					relationships, mod these to include yours.
				*/
				case BUG_CUSTOM_RELATIONSHIP_COMPONENT_OF:
					array_push($tags, "relationship-component");
					array_push($tags, "relationship-is-component-of+".$relationship->dest_bug_id);
					break;
				case BUG_CUSTOM_RELATIONSHIP_HAS_COMPONENT:
					array_push($tags, "relationship-component");
					array_push($tags, "relationship-has-component+".$relationship->dest_bug_id);
					break;
				case BUG_CUSTOM_RELATIONSHIP_UPDATE_OF:
					array_push($tags, "relationship-update");
					array_push($tags, "relationship-is-update-of+".$relationship->dest_bug_id);
					break;
				case BUG_CUSTOM_RELATIONSHIP_HAS_UPDATE:
					array_push($tags, "relationship-update");
					array_push($tags, "relationship-has-update+".$relationship->dest_bug_id);
					break;
				case BUG_CUSTOM_RELATIONSHIP_REQUIREMENT_OF:
					array_push($tags, "relationship-requirement");
					array_push($tags, "relationship-is-requirement-of+".$relationship->dest_bug_id);
					break;
				case BUG_CUSTOM_RELATIONSHIP_HAS_REQUIREMENT:
					array_push($tags, "relationship-requirement");
					array_push($tags, "relationship-has-requirement+".$relationship->dest_bug_id);
					break;
				case BUG_CUSTOM_RELATIONSHIP_MAKES_OBSOLETE:
					array_push($tags, "relationship-obsoletion");
					array_push($tags, "relationship-makes-obsolete+".$relationship->dest_bug_id);
					break;
				case BUG_CUSTOM_RELATIONSHIP_MADE_OBSOLETE:
					array_push($tags, "relationship-obsoletion");
					array_push($tags, "relationship-made-obsolete-by+".$relationship->dest_bug_id);
					break;
			}
		}
		
		return array_unique($tags);
	}
	
	/** \brief Gets a valid lpname nickname for this bug.
		\return The safe, schema-compliant nickname for this bug.
	*/
	private function getValidNickname() {
		$lpTitle = lpXmlWriter::getLpName($this->bug->__get('summary'));
		
		// if this nick was already used, we append a -i
		if(in_array($lpTitle, bugWrapper::$usedNicknames)) {
			$lpTitle .= '-i';
		}
		
		// if foo-i is also already in use, we append 'i's until we find a free one
		// (result being that we have foo, foo-i, foo-ii, foo-iii etc.)
		while(in_array($lpTitle, bugWrapper::$usedNicknames)) {
			$lpTitle .= 'i';
		}
		
		// if we end up here, we found a free nick
		return $lpTitle;
	}
	
	/** \brief Gets the full description text of this bug.
		\details Mantis is finer-grained than LaunchPad; it offers the additional
		fields of "Steps to Reproduce" and "Additional Information". In order to
		transfer all information, we have to not just take the plain description,
		but munge the additional fields into it.
		
		\sa getDescription()
		\return The full description of this bug, including the advanced fields.
	*/
	private function getFullDescription() {
		$retval = '';
		$desc = $this->bug->__get('description');
		$str = $this->bug->__get('steps_to_reproduce');
		$ai = $this->bug->__get('additional_information');
		
		if(isset($desc) && strlen($desc) > 0) {
			$retval .= $desc;
		}
		
		if(isset($str) && !is_blank($str)) {
			$retval .= PHP_EOL . PHP_EOL . PHP_EOL . '##### STEPS TO REPRODUCE #####' . PHP_EOL;
			$retval .= $str;
		}
		
		if(isset($ai) && !is_blank($ai)) {
			$retval .= PHP_EOL . PHP_EOL . PHP_EOL . '##### ADDITIONAL INFORMATION  #####' . PHP_EOL;
			$retval .= $ai;
		}
		
		return $retval;
	}
	
	/** \brief Deduces the LaunchPad status for this bug.
		\details Mantis is far finer-grained than LaunchPad; it has a status, a resolution and a reproducibility;
		Launchpad only has a status. This function takes Mantis's three indicators and distills them down
		to one LaunchPad status.
	
		\sa https://help.launchpad.net/Bugs/Statuses/External#Mantis
		\return The LaunchPad status for this bug.
	*/
	private function getLaunchPadStatus() {
		$status = $this->bug->__get('status');
		$resolution = $this->bug->__get('resolution');
		$reproducibility = $this->bug->__get('reproducibility');
		
		$retVal = '';
		
		if(($status == RESOLVED) || ($status == CLOSED)) { // if this issue is marked as resolved/closed, we go by the resolution
			switch($resolution) {
				case REOPENED:
					$retVal = lpXmlWriter::$NEW;
					break;
				
				case OPEN:
				case FIXED:
				case NOT_A_BUG:
					$retVal = lpXmlWriter::$FIXRELEASED;
					break;
				
				case UNABLE_TO_DUPLICATE:
				case NOT_FIXABLE:
				case DUPLICATE:
				case SUSPENDED:
					$retVal = lpXmlWriter::$INVALID;
					break;
				
				case WONT_FIX:
					$retVal = lpXmlWriter::$WONTFIX;
					break;
				
				default:
					$retVal = lpXmlWriter::$NEW;
					break;
			}
		} else {
			switch($status) { // if it's not resolved yet, we go by the current status
				case NEW_:
					$retVal = lpXmlWriter::$NEW;
					break;
				
				case FEEDBACK:
					$retVal = lpXmlWriter::$INCOMPLETE;
					break;
				
				case ACKNOWLEDGED: // LP's external connector marks these confirmed, but since they're not, we're converting differently
					$retVal = lpXmlWriter::$NEW; // allenap suggested the alternative of TRIAGED + importance, but that requires more info than we have (and is not how we used this)
					break;
				
				case CONFIRMED:
					$retVal = lpXmlWriter::$CONFIRMED;
					break;
				
				case ASSIGNED:
					$retVal = lpXmlWriter::$INPROGRESS;
					break;
				
				default:
					$retVal = lpXmlWriter::$NEW;
					break;
			}
			
			switch($reproducibility) { // lastly, we do a little checking of the reproducibility, to weed out bullshit-issues
				case REPRODUCIBILITY_HAVENOTTRIED: // this is surely debatable, but since I'm writing this for my own purposes first, this is how we'll handle it :P
					$retVal = lpXmlWriter::$INCOMPLETE;
					break;
				
				case REPRODUCIBILITY_UNABLETODUPLICATE:
					$retVal = lpXmlWriter::$INVALID;
					break;
			}
		}

		return $retVal;
	}
	
	/** \brief Deduces the LaunchPad importance for this bug.
		\details Mantis is finer-grained than LaunchPad; it has both a priority and a severity;
		Launchpad only has importance. This function takes Mantis's two indicators and distills them down
		to one LaunchPad importance.
	
		\sa https://help.launchpad.net/Bugs/Statuses/External#Mantis
		\return The LaunchPad importance for this bug.
	*/
	private function getLaunchPadImportance() {
		$priority = $this->bug->__get('priority');
		$severity = $this->bug->__get('severity');
		
		$retVal = '';
		
		switch($priority) {
			case NONE:
				$retVal = lpXmlWriter::$WISHLIST; // could also be LOW, I'm thinking "If we had infinite time and infinite resources, I'd also like to fix..." here
				break;
			
			case LOW:
				$retVal = lpXmlWriter::$LOW;
				break;
			
			case NORMAL:
				$retVal = lpXmlWriter::$MEDIUM;
				break;
			
			case HIGH:
				$retVal = lpXmlWriter::$HIGH;
				break;
			
			case URGENT:
			case IMMEDIATE:
				$retVal = lpXmlWriter::$CRITICAL;
				break;
			
			default:
				$retVal = lpXmlWriter::$UNDECIDED;
				break;
		}
		
		switch($severity) {
			case FEATURE:
				$retVal = lpXmlWriter::$WISHLIST;
				break;
			
			case BLOCK:
				$retVal = lpXmlWriter::$CRITICAL;
				break;
		}
		
		return $retVal;
	}
	
	/** \brief Deduces the LaunchPad milestone for this bug.
		\details Mantis is finer-grained than LaunchPad; it has both a target version and fixed-in-version-field;
		Launchpad only has one milestone. This function takes Mantis's two indicators and
		chooses one to be the LaunchPad milestone.
	
		\return The LaunchPad milestone for this bug.
	*/
	private function getMilestone() {
		$curProjName = project_get_name($this->bug->__get('project_id'));
		$targetVersion = $this->bug->__get('target_version');
		$fixedInVersion = $this->bug->__get('fixed_in_version');
		$version = '';
		
		if(!is_blank($targetVersion)) {
			$version = $targetVersion;
		} else if(!is_blank($fixedInVersion)) {
			$version = $fixedInVersion;
		}
		
		return str_replace("$curProjName ", '', $version); // LaunchPad auto-prepends the project name.
	}
	
	/**	\brief Writes the given person tag, either for the given user, or, if he doesn't exist, for Nobody.
	 
		\param $pTagName The tag name for this person.
		\param $pUserId The Id of the user to check/output.
		\return Nothing, but writes to the XML output.
	*/
	private function writePersonTag($pTagName, $pUserId) {
		if(user_exists($pUserId)) {
			$userName = user_get_name($pUserId);
			$this->xml->writePerson($pTagName, $userName, user_get_email($pUserId), $userName);
		} else {
			$this->xml->writeNobody($pTagName);
		}
	}
	
	/** \brief Extracts all URLs from the given text.
		\note This function does not support URLs containing whitespace.
		\param $pDescription a text to search in.
		\return An array containing all URLs found in the text.
	*/
	private function getUrls($pDescription) {
		$results = array();
		preg_match_all('/\w+:\/\/\S+\w/i', $pDescription, $results, PREG_PATTERN_ORDER);
		return $results[0];
	}
	
	/**	\brief This is a callback-function for array_walk() to reduce all potential tags to lpnames.
		\details This is necessary because otherwise, duplications and stop words can occur in the final
		output despite the fact that getTags should have filtered them out.\n
		e.g. Armor=Crap and armor-crap would be equal in the eyes of lpname/Launchpad.
		
		\param $pValue Reference to the current array value.
		\param $pKey Current array key.
		\return Nothing, but writes to $pArray.
	*/
	private function tagHelper(&$pValue, $pKey) {
		$pValue = lpXmlWriter::getLpName($pValue);
	}
	
	/** \brief Returns the tags for this bug.
		\details Gets the tags associated with this bug in Mantis, and returns those in any case.\n
		Additionally, if an argument is given, it'll extract the ten most-used words of three letters
		or longer from the text given and use those as tags as well. (This is to generate topical
		tags for bugs that haven't been tagged in the past.)
		
		\param $pDescription A text to extract tags from, in our case the full description.
		\return An array of strings, the extracted tags.
	*/
	private function getTags($pDescription = NULL) {
		global $stopwords; // global stopwords list
		
		// get mantis tags
		$mantisTags = tag_bug_get_attached($this->getId());
		$tags = array();
		foreach($mantisTags as $tagData) {
			array_push($tags, $tagData["name"]);
		}
		
		// generate additional tags from description text
		if(isset($pDescription)) {
			$desc = str_replace('##### ADDITIONAL INFORMATION  #####', '', str_replace('##### STEPS TO REPRODUCE #####', '', $pDescription)); // remove the section delimiters we added
			$arr = explode(" ", $desc); // create an array from the description
			array_walk($arr, array($this, 'tagHelper'));
			$sw = $stopwords;
			array_walk($sw, array($this, 'tagHelper'));
			$arr = array_diff($arr, $sw); // remove all common words ("stop words")
			$arr = array_count_values($arr); // count occurrences of the remaining words // this turns the values into the keys
			arsort($arr); // order by occurrences
			$arr = array_diff($arr, range(0,1)); // kill all words with less than 2 occurrences
			$arr = array_slice($arr, 0, 10); // pick at maximum the top ten words
			$tags = array_unique(array_merge($tags, array_keys($arr)), SORT_STRING); // add the found words to the tag list
		}
		return $tags;
	}
	
	/**	\brief Gets the subscribers to this bug.
		\return An array of person arrays, the subscribers to this bug.
	*/
	private function getSubscriptions() {
		$watchers = bug_get_monitors($this->getId());
		$subscribers = array();
		if(count($watchers) > 0) {
			foreach($watchers as $watcher) {
				if(user_exists($watcher)) {
					$userName = user_get_name($watcher);
					array_push($subscribers, lpXmlWriter::gpa($userName, user_get_email($watcher), $userName));
				}
			}
		}
		return $subscribers;
	}
	
	/**	\brief Whether this bug has comments.
		\return Boolean
	*/
	private function hasComments() {
		return ($this->commentCount > 0);
	}
	
	/** \brief Gets the name of the bugnote table for this Mantis instance.
		\details Database prefix and suffix of Mmantis installations can vary;
		this method gets the local name of the bugnote table.
	
		\return The name of the bugnote table in the database.
	*/
	private function getBugnoteTable() {
		if(!isset(bugWrapper::$bugnoteTable)) {
			//bugWrapper::$bugnoteTable = db_get_table('bugnote');
			// Mantis's shit is broken for some reason, so we're doing this the hard, but working way:
			$allTables = db_get_table_list();

			foreach($allTables as $table) {
				if(stripos($table, 'bugnote') !== false) {
					bugWrapper::$bugnoteTable = $table;
					break;
				}
			}
		}
		return bugWrapper::$bugnoteTable;
	}

	/** \brief Gets the name of the bug_file table for this Mantis instance.
		\details Database prefix and suffix of Mmantis installations can vary;
		this method gets the local name of the bugnote table.
	
		\return The name of the bug_file table in the database.
	*/	
	private function getBugfileTable() {
		if(!isset(bugWrapper::$bugfileTable)) {
			//bugWrapper::$bugfileTable = db_get_table('bug_file');
			// Mantis's shit is broken for some reason, so we're doing this the hard, but working way:
			$allTables = db_get_table_list();

			foreach($allTables as $table) {
				if(stripos($table, 'bug_file') !== false) {
					bugWrapper::$bugfileTable = $table;
					break;
				}
			}
		}
		return bugWrapper::$bugfileTable;
	}
	
	/** \brief Gets the comments for this bug.
		\details This function generates the appropriate comments array for this bug;
		this includes returning appropriate attachment arrays and creating pseudo-comments
		for standalone attachments.
		\return A comment array, including inner attachment arrays.
	*/
	private function getComments() {
		$retVal = array();
		
		// this is the bug-duplicating grandparent-comment, for use in versioning
		array_push($retVal, array(
            "date" => $this->bug->__get('date_submitted'),
            "title" => $this->bug->__get('summary'),
            "text" => $this->getDescription()
        ));
		
		if(!is_null($this->getReporter())) {
			$retVal[0]["sender"] = $this->getReporter();
		}
		
		if(!$this->hasComments() && !$this->hasAttachments) { // if there are no native comments, we can abort execution here
			return $retVal;
		}
		
		$attachments = NULL;
		if($this->hasAttachments) {
			$dbQuery = "SELECT IFNULL(bn.id, 0) AS comment_id, bf.user_id, bf.filename, bf.file_type, bf.content, bf.date_added FROM " . $this->getBugfileTable() . " bf
LEFT OUTER JOIN " . $this->getBugnoteTable() . " bn ON bf.bug_id = bn.bug_id AND bf.user_id = bn.reporter_id AND bn.date_submitted BETWEEN (bf.date_added - 60) AND (bf.date_added + 60)
WHERE bf.bug_id = " . db_param() . "
ORDER BY bf.date_added";

			$result = db_query_bound($dbQuery, array($this->getId()));
			// $result now contains just the file info we need, associated with a comment ID it probably belongs to, where applicable
			$attachments = array(); // we need this info twice, and the DB resultset is a bit pesky in that regard.
			
			// store resultset
			while($row = db_fetch_array($result)) {
				array_push($attachments, $row);
			}
			
			// attach unassociated bugs to the grandparent comment
			foreach($attachments as $a) {
				if($a['comment_id'] == 0) {
					if(!isset($retVal[0]["attachments"])) {
						$retVal[0]["attachments"] = array();
					}
	
					$anonAtt = array(
						"contents" => $a['content'],
						"patch" => (stripos($a['filename'], 'patch') !== false),
						"filename" => $a['filename'],
						"title" => $a['filename'] . " by " . user_get_name($a['user_id']), // this will blow up if the user doesn't exist anymore
						"mimetype" => $a['file_type']
					);
					
					array_push($retVal[0]["attachments"], $anonAtt);
				}
			}
		}
		
		// get the comments
		$bugnotes = bugnote_get_all_bugnotes($this->getId());
		$bnc = count($bugnotes);
		
		// write our comment arrays
		for($i = 0; $i < $bnc; ++$i) {
			// figure out which user this belongs to
			$user = NULL;
			if(user_exists($bugnotes[$i]->reporter_id) && !$this->notARealUser($bugnotes[$i]->reporter_id)) {
				$username = user_get_name($bugnotes[$i]->reporter_id);
				$user = lpXmlWriter::gpa($username, user_get_email($bugnotes[$i]->reporter_id), $username);
			}
			
			$thisComment = array(
				"date" => $bugnotes[$i]->date_submitted,
				"text" => $bugnotes[$i]->note
			);
			
			if(!is_null($user)) {
				$thisComment["sender"] = $user;
			}
			
			
			if(!is_null($attachments)) { // if $attachments is still NULL, there are no attachments, so there can't possibly be attachments we have to handle
				// this is a bit ugly, but since the data is in subarrays, it doesn't get much prettier anyhow
				foreach($attachments as $a) {
					if($a['comment_id'] == $bugnotes[$i]->id) {
						// if this comment's ID matches the comment ID of an attachment,
						// we create an attachments-array on the current comment...
						if(!isset($thisComment["attachments"])) {
							$thisComment["attachments"] = array();
						}
						
						// ...extract the attachment data...
						$anonAtt = array(
							"contents" => $a['content'],
							"patch" => (stripos($a['filename'], 'patch') !== false),
							"filename" => $a['filename'],
							"mimetype" => $a['file_type']
						);
						
						// ...and add the attachment to the attachments-array.
						array_push($thisComment["attachments"], $anonAtt);
					}
				}
			}
			
			array_push($retVal, $thisComment);
		}
		return $retVal;
	}
	
	/**	\brief Creates a new bug wrapper.
		\param $pBug Reference to the bug to provide a wrapper for.
		\param $pLpXmlWriter Reference to the lpXmlWriter instance.
	*/
	public function __construct(&$pBug, &$pLpXmlWriter) {
		$this->bug = $pBug;
		$this->xml = $pLpXmlWriter;
		assert(!is_null($this->bug));
		assert(!is_null($this->xml));
		
		$this->hasAttachments = file_bug_has_attachments($this->getId());
		$bns = bug_get_bugnote_stats($this->getId());
		$this->commentCount = $bns['count'];
	}
	
	/** \brief Generates/outputs the XML for this bug through the referenced lpXmlWriter.
	 
	*/
	public function generateXml() {
		$this->xml->startBug($this->getId());
		$this->xml->writePrivate($this->isPrivate());
		
		$originalBug = $this->getOriginal();
		if($originalBug != 0) {
			$this->xml->writeDuplicateOf($originalBug);
		}
		
		$this->xml->writeDateCreated($this->bug->__get('date_submitted'));
		$nickname = $this->getValidNickname();
		$this->xml->writeNickname($nickname);
		array_push(bugWrapper::$usedNicknames, $nickname);
		$this->xml->writeTitle($this->bug->__get('summary'));
		$this->xml->writeDescription($this->getDescription());
		if(!is_null($this->getReporter())) {
			$this->xml->writePersonA('reporter', $this->getReporter());
		} else {
			$this->xml->writeNobody('reporter');
		}
		$this->xml->writeStatus($this->getLaunchPadStatus());
		$this->xml->writeImportance($this->getLaunchPadImportance());
		$milestone = $this->getMilestone();
		if(isset($milestone) && !is_blank($milestone)) {
			$this->xml->writeMilestone($milestone);
		}
		
		$handler = $this->bug->__get('handler_id');
		if(isset($handler) && ($handler != 0) && !$this->notARealUser($handler)) {
			$this->writePersonTag('assignee', $handler);
		}
		unset($handler);
		
		// according to allenap, the <urls> section is ignored anyway, making this pointless.
		$rawUrls = $this->getUrls($this->getDescription());
		$urls = array();
		foreach($rawUrls as $number => $url) {
			$urls["Automatically extracted URL #".++$number] = $url;
		}
		if(count($urls) > 0) {
			$this->xml->writeUrls($urls);
		}
		unset($rawUrls, $urls);
		
		// we generate tags out of: Existing tags, all description parts, and the relationships.
		$this->xml->writeTags(array_merge($this->getTags($this->getDescription()), $this->getRelationshipTags()));
		
		$this->xml->writeBugwatches(array(config_get('path')."view.php?id=".$this->getId()));
		
		$subs = $this->getSubscriptions();
		if(count($subs) > 0) {
			$this->xml->writeSubscriptions($subs);
		}
		
		$this->xml->writeComments($this->getComments());
		
		$this->xml->endBug();
	}
}


// ######### Actual Work Starts Here #########
// generate output file name
$outputFileName = project_get_name(helper_get_current_project()) . '-LaunchPad-Import-' . date('Ymd-His') . ".xml";

// instantiate lpXmlWriter
$lp = new lpXmlWriter("./uploads/$outputFileName"); //! \todo I'm not sure if Mantis allows moving/configuring the upload folder, if so, this hardcoding of ./uploads/ should be fixed.

// get the bugs
$pageWeWantToSee = 1; // we want to see page 1, the start, of the virtual "view bugs" page we're on
$bugsPerPage = -1; // we want to see -1 = all bugs per page (result: all bugs are on page 1, which we're on)
$pageCount = 0; // set via reference by the API: The actual number of pages returned
$bugCount = 0; // set via reference by the API: The number of bugs returned
$bugs = filter_get_bug_rows($pageWeWantToSee, $bugsPerPage, $pageCount, $bugCount); // we now have all bugs returned by the current filter in $bugs
assert(count($bugs) > 0);
assert(count($bugs) == $bugCount);

// Show Mantis header on export page
html_page_top('LaunchPad Export Plugin');
echo "<h1>Exporting $bugCount bugs</h1>";

// RELEASE THE KRAKEN!!!!!!
// or, you know, generate the XML document
foreach($bugs as $bug) {
	$bW = new bugWrapper($bug, $lp);
	$bW->generateXml();
	unset($bW);
}

unset($lp);

// Write the Mantis footer and success notification
echo "Export ended; please check $outputFileName in your uploads folder and your error logs to see if all went well.";
html_page_bottom();