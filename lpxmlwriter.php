<?php
// based on allenap's bug-export.rnc revision 14276

class lpXmlWriter extends XMLWriter {
	private $inMemory = false;
	private $bugIsOpen = false;
	private static $result = NULL;
	
	// LaunchPad XML namespace
	public static $xmlNs = "https://launchpad.net/xmlns/2006/bugs";
	
	// LaunchPad statuses
	public static $NEW			= "NEW";
	public static $INCOMPLETE	= "INCOMPLETE";
	public static $INVALID		= "INVALID";
	public static $WONTFIX		= "WONTFIX";
	public static $CONFIRMED	= "CONFIRMED";
	public static $TRIAGED		= "TRIAGED";
	public static $INPROGRESS	= "INPROGRESS";
	public static $FIXCOMMITTED = "FIXCOMMITTED";
	public static $FIXRELEASED	= "FIXRELEASED";
	
	// LaunchPad importances
	public static $UNKNOWN		= "UNKNOWN"; // do NOT use this as a status: https://bugs.launchpad.net/launchpad/+bug/889194 (you won't be able to access your bugs)
	public static $CRITICAL		= "CRITICAL";
	public static $HIGH			= "HIGH";
	public static $MEDIUM		= "MEDIUM";
	public static $LOW			= "LOW";
	public static $WISHLIST		= "WISHLIST";
	public static $UNDECIDED	= "UNDECIDED";
	
	// LaunchPad patch statuses
	public static $PATCH		= "PATCH";
	public static $UNSPECIFIED	= "UNSPECIFIED";

	
	// LaunchPad datatypes
	// naming counterintuitive in favor of brevity: Returns LaunchPad string for given bool value
	public static function getBool($pBool) {
		return $pBool ? "True" : "False";
	}
	
	// LP is a bit conservative/pesky about allowed characters in usernames: [a-z0-9][a-z0-9\+\.\-]* is the only allowed import format
	// we'll just replace all illegal characters with a dash
	public static function getLpName($pName) {
		$retVal = preg_replace('/[^a-z0-9\+\.\-]/', '-', strtolower($pName));
		return ltrim($retVal, "+.-");
	}
	
	public static function getCveName($pName) {
		return (preg_match("/^(19|20)[0-9][0-9]-[0-9][0-9][0-9][0-9]$/", $pName) > 0) ? $pName : preg_replace("/^00/", "20", substr_replace(substr(str_pad(preg_replace("/[^0-9]/", "", $pName), 8, '0'), 0, 8), '-', 4, 0));
	}
	
	public static function getTimestamp($pTimestamp) {
		return str_replace('+00:00', 'Z', gmdate('c', $pTimestamp));
	}
	
	// constructor, takes path of output file
	public function __construct($pFilePath) {
		if(is_null($pFilePath)) {
			$this->inMemory = $this->openMemory();
		} else {
			if(!$this->openURI($pFilePath)) {
				$this->debug("Cannot open $pFilePath!"); // todo improve, exception
			}
		}
		
		$this->setIndent(true);
		$this->startDocument("1.0");
		$this->startElementNS(NULL, "launchpad-bugs", lpXmlWriter::$xmlNs);
	}
	
	public function __destruct() {
		$this->endElement(); // this *should* be launchpad-bugs
		if($this->bugIsOpen) {
			$this->debug("Closing the document even though a bug is still marked as open; please check your endBug()s are in balance with your startBug()s.");
		}
		$this->endDocument();
		lpXmlWriter::$result = $this->flush();
	}
	
	public static function getResult() { // returns the result of XMLWriter::flush of the most recent destruction
		//var_dump(lpXmlWriter::$result);
		return lpXmlWriter::$result;
	}
	
	public function writeNobody($pTagName = "person") {
		if($this->noOpenBug($pTagName)) return;
		
		$this->startElement($pTagName);
		$this->writeAttribute("name", "nobody");
		$this->endElement();
	}
	
	public function writePerson($pTagName = "person", $pUserName = "USERNAME.NOT.SET", $pEmail = "THIS.USER.HAS.NO.E-MAIL@example.com", $pFullName = NULL) {
		if($this->noOpenBug($pTagName)) return;
		
		$this->startElement($pTagName);
		$this->writeAttribute("name", lpXmlWriter::getLpName($pUserName));
		$this->writeAttribute("email", $pEmail);
		if(isset($pFullName)) {
			$this->text($pFullName);
		}
		$this->endElement();
	}
	
	public function writePersonA($pTagName = "person", array $pPersonArray) {
		$this->writePerson($pTagName, $pPersonArray["nick"], $pPersonArray["email"], $pPersonArray["full"]);
	}
	
	public function startBug($pId) {
		if($this->bugIsOpen) {
			$this->debug("Starting a new bug despite the previous one not seeming to be closed; did you call endBug() first?");
		}
		$this->startElementNS(NULL, "bug", lpXmlWriter::$xmlNs);
		$this->writeAttribute("id", ((isset($pId) && is_int($pId)) ? $pId : rand())); // according to the schema, id is not optional, therefore we set a random number if none is given
		$this->bugIsOpen = true;
	}
	
	public function endBug() {
		if(!$this->bugIsOpen) {
			$this->debug("Closing bug even though none seems open; did you call startBug(\$pId) first?");
		}
		$this->endElement();
		$this->bugIsOpen = false;
	}
	
	public function writeBoolean($pTagName, $pBoolean) {
		$this->writeStuff($pTagName, lpXmlWriter::getBool($pBoolean));
	}
	
	public function writePrivate($pIsPrivate = true) {
		$this->writeBoolean("private", $pIsPrivate);
	}
	
	public function writeSecurityRelated($pIsSecurityRelated = true) {
		$this->writeBoolean("security_related", $pIsSecurityRelated);
	}
	
	public function writeDuplicateOf($pExistingId) {
		if(!is_int($pExistingId)) {
			$this->debug("Duplicate ID \"$pExistingId\" is not a valid integer; tag not written.");
			return;
		}
		$this->writeStuff("duplicateof", $pExistingId);
	}
	
	public function writeDateCreated($pTimestamp) { // unix timestamp
		$this->writeStuff("datecreated", lpXmlWriter::getTimestamp($pTimestamp));
	}
	
	public function writeNickname($pNickname) {
		$this->writeStuff("nickname", lpXmlWriter::getLpName($pNickname));
	}
	
	public function writeTitle($pTitle) {
		$this->writeStuff("title", $pTitle);
	}
	
	public function writeDescription($pDescription) {
		$this->writeStuff("description", $pDescription);
	}
	
	public function writeStatus($pStatus) {
		$this->writeStuff("status", $pStatus);
	}
	
	public function writeImportance($pImportance) {
		$this->writeStuff("importance", $pImportance);
	}
	
	public function writeMilestone($pMilestone) {
		$this->writeStuff("milestone", lpXmlWriter::getLpName($pMilestone));
	}
	
	// array in the format of "url text" => "url"
	public function writeUrls(array $pUrls) {
		if(isset($pUrls)) {
			$this->startElement("urls");
			foreach($pUrls as $title => $url) {
				$this->startElement("url");
				$this->writeAttribute("href", $url);
				$this->text($title);
				$this->endElement();
			}
			$this->endElement();
		} else {
			$this->debug("writeUrls() was called without a properly set array.");
		}
	}
	
	// array of CVE-names
	public function writeCves(array $pCves) {
		foreach($pCves as $key => $value) {
			$pCves[$key] = lpXmlWriter::getCveName($value);
		}
		
		$this->writeStuffArray("cves", "cve", $pCves);
	}
	
	// array of tags
	public function writeTags(array $pTags) {
		foreach($pTags as $key => $value) {
			$pTags[$key] = lpXmlWriter::getLpName($value);
		}
		
		$this->writeStuffArray("tags", "tag", $pTags);
	}
	
	// array of bugwatch-urls
	public function writeBugwatches(array $pUrls) {
		if(isset($pUrls)) {
			$this->startElement("bugwatches");
			foreach($pUrls as $url) {
				$this->startElement("bugwatch");
				$this->writeAttribute("href", $url);
				$this->endElement();
			}
			$this->endElement();
		} else {
			$this->debug("writeBugwatches() was called without a properly set array.");
		}
	}
	
	// array of arrays with keys "nick", "email" and "full"
	public function writeSubscriptions(array $pSubs) {
		if(isset($pSubs)) {
			$this->startElement("subscriptions");
			foreach($pSubs as $sub) {
				$this->writePersonA("subscriber", $sub);
			}
			$this->endElement();
		} else {
			$this->debug("writeBugwatches() was called without a properly set array.");
		}
	}
	
	/*
		array of arrays; inner arrays contain keys:
			- sender
				array, keys:
					- nick
					- email
					- full
			- date (unix timestamp)
			- title (optional)
			- text
			- attachments (array of attachment-arrays, optional)
	
	*/
	public function writeComments(array $pComments) {
		if(!isset($pComments)) {
			$this->debug("Comment array was not set.");
			return;
		}
		
		$commentCount = count($pComments);
		for($i=0; $i < $commentCount; ++$i) {
			$p = $pComments[$i];
			
			$this->startElement("comment");
			
			$this->writePersonA("sender", $p["sender"]);
			$this->writeStuff("date", lpXmlWriter::getTimestamp($p["date"]));
			if(isset($p["title"])) {
				$this->writeStuff("title", $p["title"]);
			}
			$this->writeStuff("text", $p["text"]);
			
			if(isset($p["attachments"])) {
				$this->writeAttachments($p["attachments"]);
			}
			
			$this->endElement();
		}
	}
	
	
	/* array of attachment arrays; inner arrays contain keys:
			mandatory:
		- contents (raw file contents, function takes care of encoding)
		
			optional:
		- url
		- patch (boolean)
		- filename
		- title
		- mimetype
	*/
	public function writeAttachments(array $pAttachments) {
		if(!isset($pAttachments)) {
			$this->debug("Attachment array was not set.");
			return;
		}
		
		$attachmentCount = count($pAttachments);
		for($i=0; $i < $attachmentCount; ++$i) {
			$a = $pAttachments[$i];
			
			$this->startElement("attachment");
			if(isset($a["url"])) {
				$this->writeAttribute("href", $a["url"]);
			}
			if(isset($a["patch"])) {
				$this->writeStuff("type", ($a["patch"] ? lpXmlWriter::$PATCH : lpXmlWriter::$UNSPECIFIED));
			}
			if(isset($a["filename"]) && (strlen($a["filename"]) > 0)) {
				$this->writeStuff("filename", $a["filename"]);
			}
			
			if(isset($a["title"])) {
				$this->writeTitle($a["title"]);
			}
			
			if(isset($a["mimetype"])) {
				$this->writeStuff("mimetype", $a["mimetype"]);
			}
			
			if(isset($a["contents"]) && (strlen($a["contents"]) > 0)) {
				$this->writeStuff("contents", base64_encode($a["contents"]));
			} else {
				$this->debug("Content for attachment is empty; this violates LP's schema and will not validate.");
			}
			
			$this->endElement();
		}
	}
	
	
	
	// public helper functions
	// returns a properly formatted array for those functions needing users like that
	public static function getPersonArray($pUsername, $pEmail, $pFullName) {
		return array("nick" => $pUsername, "email" => $pEmail, "full" => $pFullName);
	}
	public static function gpa($pUsername, $pEmail, $pFullName) {
		return lpXmlWriter::getPersonArray($pUsername, $pEmail, $pFullName);
	}
	
	// private helper functions
	private function writeStuff($pTagName, $pStuff) {
		if($this->noOpenBug($pTagName)) return;
		if(!isset($pTagName)) {
			$this->debug("Could not write tag for content $pStuff because no tag name was given!");
			return;
		}
		if(!isset($pStuff)) {
			$this->debug("$pTagName was not written because no content was given.");
			return;
		}
		$this->writeElement($pTagName, strip_tags($pStuff));
	}
	
	private function writeStuffArray($pOuterTag, $pInnerTag, array $pContents) {
		if(!isset($pOuterTag) || !isset($pInnerTag)) {
			$this->debug("Could not output list of tags because one of the tag names was missing. Hint: $pOuterTag $pInnerTag");
			return;
		}
		if(!isset($pContents)) {
			$this->debug("write$pOuterTag() was called without a properly set array.");
			return;
		}
		$this->startElement($pOuterTag);
		foreach($pContents as $content) {
			$this->writeStuff($pInnerTag, $content);
		}
		$this->endElement();
	}
	
	private function noOpenBug($pTag = "tag") {
		if(!$this->bugIsOpen) {
			$this->debug("Could not write $pTag because no bug was open.");
		}
		return !$this->bugIsOpen;
	}
	
	private function debug($pMsg) {
		$this->writeComment("DEBUG MESSAGE: ".$pMsg);
	}
}
/*
	public function writeDescription($pDescription, $pStepsToReproduce = NULL, $pAdditionalInformation = NULL) {
		$fullText = $pDescription;
		if(isset($pStepsToReproduce)) {
			$fullText .= PHP_EOL . PHP_EOL . "Steps to Reproduce:" . PHP_EOL . $pStepsToReproduce;
		}
		if(isset($pAdditionalInformation)) {
			$fullText .= PHP_EOL . PHP_EOL . "Additional Information:" . PHP_EOL . $pAdditionalInformation;
		}
	}
*/