<?php
/*
    LaunchPad XMLWriter Extension is an extension of PHP's XMLWriter class that
    provides a number of helper methods and presets to speed up generation of
    LaunchPad bug-import XML files.
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

/** \mainpage LaunchPad XMLWriter Extension
	The LaunchPad XMLWriter Extension extends PHP's standard XMLWriter class with
	a number of constants and methods to speed up generation of LaunchPad bug-import 
	XML files.
	
	\section usage Usage
	Usage is easy:
	 - Add \code require_once("lpxmlwriter.php"); \endcode at the start of your PHP file, adjusting the path if necessary.
	 - Instantiate an lpXmlWriter: \code $lp = new lpXmlWriter("output.xml"); \endcode
	 - lpXmlWriter automatically writes the header and root elements.
	 - Use lpXmlWriter's methods to write the individual bug data.
	 - When done, unset your instance \code unset($lp); \endcode lpXmlWriter
	   automatically closes the root element and flushes the XML to the target.
	
	\section info Information
	The LaunchPad XMLWriter Extension was written by Charly "Renegade" Kiendl in
	November 2011 based on Gavin "allenap" Panella's revision 14276 of LaunchPad's
	\c bug-export.rnc.\n
	It is licensed under GPL v3.
	
	\example lpxmlwriter_example.php
*/
/** \page arrays Arrays
	\section person Person Arrays
	Person arrays are used to concisely hand over the three elements making up a
	LaunchPad person definition: Username, e-mail-address and full name.
	\par Format
	\code
		array(
			"nick" => "Some Username",
			"email" => "foo@example.com",
			"full" => "Some Full Name"
		);
	\endcode
	\note Manual creation of person arrays is \e not necessary: lpXmlWriter offers
	a static function lpXmlWriter::getPersonArray() with which the same array can be
	acquired like so:
	\code
		lpXmlWriter::getPersonArray("Some Username", "foo@example.com", "Some Full Name");
	\endcode
	\par
	\note LaunchPad's system expects an \c lpname for the username; lpXmlWriter's
	functions are generally coded to automatically convert the arguments to the
	required format, but if you're writing to the XML directly, make sure to use
	lpXmlWriter::getLpName() to sanitize your output.
	
	\section url URL Arrays
	URL arrays are used to hand over the list of URLs associated with a bug.
	\par Format
	The format is simply that of using the desired URL title as the key, and the 
	actual URL as the value:
	\code
		array(
			"This is link 1" => "https://example.com",
			"This is link 2" => "https://example.test"
		);
	\endcode
	\warning Make sure your link titles are unique, so the links don't overwrite each other in the array.
	
	\section comment Comment Arrays
	These arrays embody a bug comment with all its metadata.
	\par Format
	\code
		array(
			"sender" => lpXmlWriter::getPersonArray("Some Username", "foo@example.com", "Some Full Name"),
			"date" => 1234567890,
			"title" => "The comment's title (optional)",
			"text" => "The complete text of the comment",
			"attachments" => array(array(), array())
		);
	\endcode

	As visible, the \c sender part should contain a \ref person "person array";
	the \c attachments key should contain an array of \ref attachment "attachment arrays" (see below).\n
	Like the \c title key, the \c attachments key is optional.\n
	\c date should contain a Unix timestamp.
	
	\section attachment Attachment Arrays
	Attachment arrays contain both the attachment's metadata as well as the actual
	file content, base64-encoded; lpXmlWriter does that encoding automatically,
	so if you're working with lpXmlWriter's own methods, just dump the file contents
	unchanged into the \c contents key.
	\par Format
	\code
		array(
			"contents" => "The actual file contents",
			"url" => "http://thisleadsnowhere.example",
			"patch" => true,
			"filename" => "some_file_name.patch",
			"title" => "Awesome Example Patch I",
			"mimetype" => "text/plain"
		);
	\endcode

	All keys but \c contents are optional.\n
	\c patch is a true boolean, it gets translated to LaunchPad schema boolean by
	lpXmlWriter down the line. It signifies whether this attachment is a patch or not.
*/

class lpXmlWriter extends XMLWriter {
	private $inMemory = false; //!< Currently unused; if the XML data is forced into memory, this variable will contain the success-boolean of openMemory().
	private $bugIsOpen = false; //!< Whether there currently is an unclosed bug element. \sa noOpenBug()
	private static $result = NULL; //!< The return value of XML writer's flush() operation: In memory mode, the XML document, in URI mode, the number of bytes written. \sa getResult()
	
	//! LaunchPad XML namespace
	public static $xmlNs = "https://launchpad.net/xmlns/2006/bugs";
	
	/** \name LaunchPad Statuses
		Static pseudo-constants for LaunchPad's statuses. You should prefer using 
		these in your code rather than hardcoding the current values, in case something changes.
	*/
	//@{
	public static $NEW			= "NEW";
	public static $INCOMPLETE	= "INCOMPLETE";
	public static $INVALID		= "INVALID";
	public static $WONTFIX		= "WONTFIX";
	public static $CONFIRMED	= "CONFIRMED";
	public static $TRIAGED		= "TRIAGED";
	public static $INPROGRESS	= "INPROGRESS";
	public static $FIXCOMMITTED = "FIXCOMMITTED";
	public static $FIXRELEASED	= "FIXRELEASED";
	//@}
	
	/** \name LaunchPad Importances
		Static pseudo-constants for LaunchPad's importances. You should prefer using 
		these in your code rather than hardcoding the current values, in case something changes.
	*/
	//@{
	public static $UNKNOWN		= "UNKNOWN"; //!< \warning Do NOT use this as a status - you will not be able to access your bugs! \sa https://bugs.launchpad.net/launchpad/+bug/889194
	public static $CRITICAL		= "CRITICAL";
	public static $HIGH			= "HIGH";
	public static $MEDIUM		= "MEDIUM";
	public static $LOW			= "LOW";
	public static $WISHLIST		= "WISHLIST";
	public static $UNDECIDED	= "UNDECIDED";
	//@}
	
	// LaunchPad patch statuses
	/** \name LaunchPad Attachment Type Identifiers
		Static pseudo-constants for LaunchPad's attachment type identifiers. You 
		should prefer using these in your code rather than hardcoding the current 
		values, in case something changes.
	*/
	//@{
	public static $PATCH		= "PATCH";
	public static $UNSPECIFIED	= "UNSPECIFIED";
	//@}

	
	/** \name LaunchPad Datatypes
		LaunchPad's schema defines a number of datatypes; these helper functions
		ease the process of properly converting your data.
		
		They are all static, so you can use them independently in your own writer as well.
	*/
	//@{
	
	//! 
	/**
		\brief Returns LaunchPad string for given bool value.
		\details This function converts the given boolean value into the proper textual representation allowed by LP's schema.
	
		\param $pBool The boolean to convert.
		\return The proper LaunchPad XML representation of the given boolean.
	*/
	public static function getBool($pBool) {
		return $pBool ? "True" : "False";
	}
	
	/**
		\brief Gets the lpname from the given string.
		\details LaunchPad's schema defines an "lpname" datatype which represents
		strings that LaunchPad accepts as nicknames for certain objects; these lpnames
		are severely limited in that they can only consist of lowercase A-Z, 0-9 and
		the plus, minus and period characters, starting only with the former ones.
		
		This function converts a given string into a legal lpname.
		\warning The function is in no way gentle with its conversion: It simply converts
		the argument to lowercase and turns all illegal characters into dashes.\n
		If you need something more sophisticated, you need to roll your own method.
		
		\param $pName The string to convert.
		\return The lpname representation of the given string.
	*/
	public static function getLpName($pName) {
		$retVal = preg_replace('/-{2,}/', '-', preg_replace('/[^a-z0-9\+\.\-]/', '-', strtolower($pName)));
		return trim($retVal, "+.-");
	}
	
	/**
		\brief Converts the given text into a CVE-ID.
		\details LaunchPad's schema mandates that Common Vulnerabilities and Exposures Identifiers (CVE-IDs)
		adhere to a very precise and narrow format; this function checks if the given text
		complies with the specification, and performs modifications to enforce them if it doesn't.
		
		\warning Realistically, if the input to this function is not in CVE-ID-format already, it likely
		never was a CVE-ID in the first place. As such, the modifications performed by this function 
		primarily serve the purpose of ensuring schema compliance to prevent parsing errors, rather than
		trying to salvage broken IDs.
		\par
		Therefore, the "algorithm" behind this is rather crude: It kills anything that's not a number,
		ensures what's left are exactly 8 digits, and then inserts a dash in the middle.
		\par
		If you have differently-formatted CVE-IDs in your source, it is \b strongly recommended that you write
		a custom transformation function instead of relying on this one.
		
		\param $pName A CVE-ID to check.
		\return A properly formatted CVE-ID.
	*/
	public static function getCveName($pName) {
		return (preg_match("/^(19|20)[0-9][0-9]-[0-9][0-9][0-9][0-9]$/", $pName) > 0) ? $pName : preg_replace("/^00/", "20", substr_replace(substr(str_pad(preg_replace("/[^0-9]/", "", $pName), 8, '0'), 0, 8), '-', 4, 0));
	}
	
	/**
		\brief Gets a schema-conform timestamp from a Unix timestamp.
		\details LaunchPad's schema expects an ISO 8601 combined date and time in UTC,
		with the proper Z-suffix instead of PHP's +0000 offset.\n
		This function converts a given Unix timestamp to that format.
		
		\param $pTimestamp The Unix timestamp of the desired point in time.
		\return An ISO 8601 timestamp with Z-suffix (string).
	*/
	public static function getTimestamp($pTimestamp) {
		return gmdate("Y-m-d\TH:i:s\Z", $pTimestamp);
		
	}
	//@}
	
	//! \name Creation- and destruction-related methods
	//@{
	/** \brief Instantiates a new lpXmlWriter.
		\details Automatically called on instantiation, this method sets up the
		output parameters for the XML, starts the document and opens the root element.
		
		\param $pFilePath Designed/intended to be the path of the output file, but
		can be any XMLWriter::openURI()-compatible URI, like php://output.
		\note If this parameter is NULL, lpXmlWriter writes to memory instead. In
		that case, you \e will have to \code echo lpXmlWriter::getResult(); \endcode
		to get the XML.
		\par
		If your desire is to make the current page the XML document, instead of
		giving NULL as the argument, consider using
		\code
<?php
	require_once("lpxmlwriter.php");
	header("Content-type: text/xml");

	$lp = new lpXmlWriter("php://output");
	
	// ...your code here...
		\endcode
		instead; this will make the current page the XML document without the
		need to retrieve the buffer from memory after deallocation.
		\return Nothing, but writes to the XML output.
	*/
	public function __construct($pFilePath) {
		if(!isset($pFilePath)) {
			$this->inMemory = $this->openMemory();
		} else {
			if(!$this->openURI($pFilePath)) {
				$this->debug("Cannot open $pFilePath!"); // todo improve, exception
			}
		}
		
		$this->setIndent(true);
		$this->startDocument("1.0", 'UTF-8');
		$this->startElementNS(NULL, "launchpad-bugs", lpXmlWriter::$xmlNs);
	}
	
	/** \brief Destroys the current lpXmlWriter.
		\details Automatically called on destruction, this method closes the root
		element and flushes the written XML out to its target.
		
		It also sets lpXmlWriter::$result for later inspection.
		\sa getResult()
		\return Nothing, but writes to the XML output.
	*/
	public function __destruct() {
		$this->endElement(); // this *should* be launchpad-bugs
		if($this->bugIsOpen) {
			$this->debug("Closing the document even though a bug is still marked as open; please check your endBug()s are in balance with your startBug()s.");
		}
		$this->endDocument();
		lpXmlWriter::$result = $this->flush();
	}
	
	/** \brief Returns the results of the latest flush operation.
		\details XMLWriter::flush() returns the generated XML buffer or the number
		of written bytes, depending on mode of operation; lpXmlWriter's destructor
		saves the output of the latest flush to a static variable, in case inspection
		by the coder is desired/required.\n
		getResult() returns the contents of said variable.
		
		\return The written XML or the number of bytes written, depending on the previous mode of operation.
	*/
	public static function getResult() { // returns the result of XMLWriter::flush of the most recent destruction
		return lpXmlWriter::$result;
	}
	//@}
	
	/** \name Methods for writing person tags.
		The methods in this group output complete person tags when called.
	*/
	//@{
	
	/** \brief Writes the person tag for the nobody/anonymous placeholder.
		\param $pTagName The name of the tag the data should be enclosed in.
		\return Nothing, but writes to the XML output.
	*/
	public function writeNobody($pTagName = "person") {
		if($this->noOpenBug($pTagName)) return;
		
		$this->startElement($pTagName);
		$this->writeAttribute("name", "nobody");
		$this->endElement();
	}
	
	/** \brief Writes a complete person tag.
		
		\note The username, in this case, is a LaunchPad-username, registered or not;
		this means it will be an \e lpname in the XML file. If your users can select
		usernames beyond lpname standards, for example containing spaces, consider
		setting the actual username as the full name ($pFullName), so that users
		keep their usual username on LaunchPad.
		\param $pTagName The name of the tag the data should be enclosed in.
		\param $pUserName The system-wise nickname of the user. Will be converted to lpname.
		\param $pEmail The user's e-mail address.
		\param $pFullName Optional; the "full", proper name of the user.
		\return Nothing, but writes to the XML output.
	*/
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
	
	/** \brief Writes a complete person tag from a \ref person "person array".
		\details Pseudo-overload for writePerson(); calls writePerson() with the
		data from the provided \ref person "person array".
		
		\sa writePerson()
		\sa getPersonArray()
		\param $pTagName The name of the tag the data should be enclosed in.
		\param $pPersonArray A \ref person "person array".
		\return Nothing, but writes to the XML output.
	*/
	public function writePersonA($pTagName = "person", array $pPersonArray) {
		$this->writePerson($pTagName, $pPersonArray["nick"], $pPersonArray["email"], $pPersonArray["full"]);
	}
	//@}
	
	//! \name Bug initialization and finalization methods.
	//@{
	/** \brief Starts a new bug element.
		\details Use this method to signal the beginning of a new bug element to the system.
		
		\param $pId The ID your bug has in your current bug tracker; this field is required and is required by the schema to be an integer.
		\warning If this parameter is NULL or not an integer, lpXmlWriter will use a random number instead, to comply with the schema.
		\return Nothing, but writes to the XML output.
	*/
	public function startBug($pId) {
		if($this->bugIsOpen) {
			$this->debug("Starting a new bug despite the previous one not seeming to be closed; did you call endBug() first?");
		}
		$this->startElementNS(NULL, "bug", lpXmlWriter::$xmlNs);
		$this->writeAttribute("id", ((isset($pId) && is_int($pId)) ? $pId : rand())); // according to the schema, id is not optional, therefore we set a random number if none is given
		$this->bugIsOpen = true;
	}
	
	/**	\brief Ends the current bug element.
		\details Signals the end of the current bug element to the system.
		
		\return Nothing, but writes to the XML output.
	*/
	public function endBug() {
		if(!$this->bugIsOpen) {
			$this->debug("Closing bug even though none seems open; did you call startBug(\$pId) first?");
		}
		$this->endElement();
		$this->bugIsOpen = false;
	}
	//@}
	
	//! \name Single-item Output Functions
	//@{
	/**	\brief Writes a boolean-containing tag to the document.
		
		\param $pTagName The name of the tag to write.
		\param $pBoolean true if true should be written.\n I'll let you figure out the rest.
		\return Nothing, but writes to the XML output.
	*/
	public function writeBoolean($pTagName, $pBoolean) {
		$this->writeStuff($pTagName, lpXmlWriter::getBool($pBoolean));
	}
	
	/**	\brief Writes the \<private\> tag to the document.
		
		\param $pIsPrivate Whether this bug is private or not. Optional, defaults to true.
		\return Nothing, but writes to the XML output.
	*/
	public function writePrivate($pIsPrivate = true) {
		$this->writeBoolean("private", $pIsPrivate);
	}
	
	/**	\brief Writes the \<security_related\> tag to the document.
		
		\param $pIsSecurityRelated Whether this bug is security related or not. Optional, defaults to true.
		\return Nothing, but writes to the XML output.
	*/
	public function writeSecurityRelated($pIsSecurityRelated = true) {
		$this->writeBoolean("security_related", $pIsSecurityRelated);
	}
	
	/**	\brief Writes the \<duplicateof\> tag to the document.
		
		\param $pExistingId ID of the bug this bug is a duplicate of. Must be an integer.
		\return Nothing, but writes to the XML output.
	*/
	public function writeDuplicateOf($pExistingId) {
		if(!is_int($pExistingId)) {
			$this->debug("Duplicate ID \"$pExistingId\" is not a valid integer; tag not written.");
			return;
		}
		$this->writeStuff("duplicateof", $pExistingId);
	}
	
	/**	\brief Writes the \<datecreated\> tag to the document.
		
		\param $pTimestamp The Unix timestamp of the point in time this bug was created on.
		\return Nothing, but writes to the XML output.
	*/
	public function writeDateCreated($pTimestamp) { // unix timestamp
		$this->writeStuff("datecreated", lpXmlWriter::getTimestamp($pTimestamp));
	}
	
	/**	\brief Writes the \<nickname\> tag to the document.
		
		\param $pNickname The nickname this bug should have. Gets sanitized to lpname datatype.
		\return Nothing, but writes to the XML output.
	*/
	public function writeNickname($pNickname) {
		$this->writeStuff("nickname", lpXmlWriter::getLpName($pNickname));
	}
	
	/**	\brief Writes the \<title\> tag to the document.
		
		\param $pTitle The title or summary of this bug.
		\return Nothing, but writes to the XML output.
	*/
	public function writeTitle($pTitle) {
		$this->writeStuff("title", $pTitle);
	}
	
	/**	\brief Writes the \<description\> tag to the document.
		
		\param $pDescription The description of this bug.
		\return Nothing, but writes to the XML output.
	*/
	public function writeDescription($pDescription) {
		$this->writeStuff("description", $pDescription);
	}
	
	/**	\brief Writes the \<status\> tag to the document.
		
		\param $pStatus The current status of this bug. Should be a value from the
		LaunchPad Statuses group of static variables.
		\return Nothing, but writes to the XML output.
	*/
	public function writeStatus($pStatus) {
		$this->writeStuff("status", $pStatus);
	}
	
	/**	\brief Writes the \<importance\> tag to the document.
		
		\param $pImportance The current importance of this bug. Should be a value from the
		LaunchPad Importances group of static variables.
		\return Nothing, but writes to the XML output.
	*/
	public function writeImportance($pImportance) {
		$this->writeStuff("importance", $pImportance);
	}
	
	/**	\brief Writes the \<milestone\> tag to the document.
		
		\param $pMilestone The milestone this bug is associated with. Gets sanitized
		to lpname datatype. LaunchPad bug import will auto-create missing milestones.
		\return Nothing, but writes to the XML output.
	*/
	public function writeMilestone($pMilestone) {
		$this->writeStuff("milestone", lpXmlWriter::getLpName($pMilestone));
	}
	//@}
	
	//! \name Multi-item Output Functions
	//@{
	/**	\brief Writes the list of associated URLs to the bug.
		\details This function takes an array of URLs and writes the complete \<urls\>
		element with all inner \<url\> elements.
		
		\param pUrls A \ref url "URL array".
		\return Nothing, but writes to the XML output.
	*/
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
	
	/**	\brief Writes the list of related CVE-IDs for this bug.
	  	\details Takes an array of CVE-IDs and writes the complete \<cves\>
		element with all inner \<cve\> elements.
		
		\note The function filters the input through getCveName() on its own,
		there is no need to sanitize it beforehand.
		\param $pCves An array of strings, those being the CVE-IDs related to this bug.
		\return Nothing, but writes to the XML output.
	*/
	public function writeCves(array $pCves) {
		foreach($pCves as $key => $value) {
			$pCves[$key] = lpXmlWriter::getCveName($value);
		}
		
		$this->writeStuffArray("cves", "cve", $pCves);
	}
	
	/**	\brief Writes the list of related tags for this bug.
	  	\details Takes an array of tags and writes the complete \<tags\>
		element with all inner \<tag\> elements.
		
		\note The function filters the input through getLpName() on its own,
		there is no need to sanitize it beforehand.
		\param $pTags An array of strings, those being the tags related to this bug.
		\return Nothing, but writes to the XML output.
	*/
	public function writeTags(array $pTags) {
		if(!isset($pTags) || (count($pTags) < 1)) {
			return;
		}
		foreach($pTags as $key => $value) {
			$pTags[$key] = lpXmlWriter::getLpName($value);
		}
		
		$this->writeStuffArray("tags", "tag", $pTags);
	}
	
	/**	\brief Writes the list of <a href="http://blog.launchpad.net/bug-tracking/launchpads-bug-watch-system-and-other-animals">bugwatches</a> for this bug.
	  	\details Takes an array of bugwatch-URLs and writes the complete \<bugwatches\>
		element with all inner \<bugwatch\> elements.
		
		\note The URLs are inserted into the document as they are provided, since
		lpXmlWriter has no way of checking whether they are correct or not; please
		double-check their correct association and insertion as part of your quality assurance.
		\param $pUrls An array of strings, those being the URLs of this bug in the current, remote or legacy bugtrackers.
		\return Nothing, but writes to the XML output.
	*/
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
	
	/**	\brief Writes the list of subscribers/watchers/monitors for this bug.
	  	\details Takes an array of \ref person "person arrays" and writes the complete \<subscriptions\>
		element with all inner \<subscriber\> elements.
		
		\param $pSubs An array of \ref person "person arrays", those being the people
		who signed up to receiving notifications of changes to this bug.
		\return Nothing, but writes to the XML output.
	*/
	public function writeSubscriptions(array $pSubs) {
		if(isset($pSubs)) {
			$this->startElement("subscriptions");
			foreach($pSubs as $sub) {
				$this->writePersonA("subscriber", $sub);
			}
			$this->endElement();
		} else {
			$this->debug("writeSubscriptions() was called without a properly set array.");
		}
	}
	
	/**	\brief Writes the complete comment section for this bug.
	  	\details This function writes the complete comment section including attachments to the document.\n
	  	You should only ever need to call this once for every bug.
		
		\param $pComments An array of \ref comment "comment arrays", the complete comment
		section including attachments of the current bug.
		\return Nothing, but writes to the XML output.
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
			
			if(isset($p["sender"])) {
				$this->writePersonA("sender", $p["sender"]);
			} else {
				$this->writeNobody("sender");
			}
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
	
	
	/**	\brief Writes the list of attachments for this comment.
	  	\details This function writes the list of attachments for the current comment
	  	from the provided array.
		
		\note Under normal circumstances, you should \e never have to call this function.\n
		Properly insert your \ref attachment "attachment arrays" into your comments array
		and call writeComments() instead.
		
		\note Should you be looking at this function because your bugtracker associates
		attachments with the bug, not with comments, consider inserting fake, empty comments
		for every attachment, or creating one fake comment containing all attachments.\n
		The attachments \e must be associated with comments, that's just how LaunchPad works.
		
		\param $pAttachments An array of \ref attachment "attachment arrays", containing
		all attachments associated with the current comment.
		\return Nothing, but writes to the XML output.
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
	//@}
	
	
	//! \name Public Helper Functions
	//@{
	/**	\brief Creates a \ref person "person array" out of the given arguments.
		\param $pUsername The user's username.
		\note This function does not sanitize the username, since the actual output
		functions of lpXmlWriter would do that later on. Keep that in mind if you use
		getPersonArray() outside the confines of lpXmlWriter.
		\param $pEmail The user's e-mail-address.
		\param $pFullName The user's full name.
		\return A \ref person "person array" of the given data.
	*/
	public static function getPersonArray($pUsername, $pEmail, $pFullName) {
		return array("nick" => $pUsername, "email" => $pEmail, "full" => $pFullName);
	}
	/**	\brief A shorthand for getPersonArray().
		\sa getPersonArray()
	*/
	public static function gpa($pUsername, $pEmail, $pFullName) {
		return lpXmlWriter::getPersonArray($pUsername, $pEmail, $pFullName);
	}
	//@}
	
	//! \name Private Helper Functions
	//@{
	/**	\brief Writes the given tag with the given content.
	 	\details This is a "low level" writing function, performing the actual writing
	 	of a single, content-only tag using safeguards to ensure the operation can be
	 	performed properly. In other words: The tag will only be written if both
	 	arguments are given and there is an open bug to write into.
	 	
	 	\note HTML entities get decoded into UTF-8 characters, HTML tags are simply
	 	stripped. Make sure your input is aligned for this treatment.
	 	
	 	\param $pTagName The name of the tag to be written.
	 	\param $pStuff The content of the tag to be written.
	 	\return Nothing, but writes to the XML output.
	*/
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
		$this->writeElement($pTagName, html_entity_decode(strip_tags($pStuff), ENT_QUOTES, 'UTF-8'));
	}
	
	/**	\brief Writes a list of tags with a surrounding grouping tag.
	 	\details Helper for situations in which a group of tags needs to be written
	 	with a surrounding grouping tag.
	 	
	 	\param $pOuterTag Name of the surrounding group tag.
	 	\param $pInnerTag Name of the tag each of the list items will have.
	 	\param $pContents Array of content items that should be surrounded by the given tags.
		\return Nothing, but writes to the XML output.
	*/
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
	
	/**	\brief Whether no bug tag is open at the moment.
	 	\details This function is a debug helper/safeguard to ensure inner bug elements
	 	are not written straight into the document.\n
	 	If no bug is open at the time of execution, it writes a debug comment saying that into the document.
	 
		\param $pTag Optional; the tag name attempting to be written. Is used in the debug message, should there be one.
		\return The function returns the inverse of lpXmlWriter::$bugIsOpen, that is,
		it returns true if no bug element is currently open.
	*/
	private function noOpenBug($pTag = "tag") {
		if(!$this->bugIsOpen) {
			$this->debug("Could not write $pTag because no bug was open.");
		}
		return !$this->bugIsOpen;
	}
	
	//! Writes a debug comment into the XML document.
	/*
		\param $pMsg The message to be written.
		\return Nothing, but writes to the XML output.
	*/
	private function debug($pMsg) {
		$this->writeComment("DEBUG MESSAGE: ".$pMsg);
	}
	//@}
}