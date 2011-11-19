<?php
// based on allenap's bug-export.rnc revision 14276

class LpXmlWriter extends XMLWriter {
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

	
	// LaunchPad datatypes
	// naming counterintuitive in favor of brevity: Returns LaunchPad string for given bool value
	public static function getBool($pBool) {
		return $pBool ? "True" : "False";
	}
	
	// LP is a bit conservative/pesky about allowed characters in usernames: [a-z0-9][a-z0-9\+\.\-]* is the only allowed import format
	// we'll just replace all illegal characters with a dash
	public static function getLpName($pName) {
		$retVal = preg_replace('/[^a-z0-9\+\.\-]/', '-', strtolower($pName));
		while(!ctype_alnum($retVal[0]) && (strlen($retVal) > 0)) {
			$retVal = ltrim($retVal, $retVal[0]);
		}
		
		return $retVal;
	}
	
	public static function getCveName($pName) {
		return $pName; // todo validate against rxp
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
		$this->startElementNS(NULL, "launchpad-bugs", LpXmlWriter::$xmlNs);
	}
	
	public function __destruct() {
		$this->endElement(); // this *should* be launchpad-bugs
		if($this->bugIsOpen) {
			$this->debug("Closing the document even though a bug is still marked as open; please check your endBug()s are in balance with your startBug()s.");
		}
		$this->endDocument();
		LpXmlWriter::$result = $this->flush();
	}
	
	public static function getResult() { // returns the result of XMLWriter::flush of the most recent destruction
		return LpXmlWriter::$result;
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
		$this->writeAttribute("name", LpXmlWriter::getLpName($pUserName));
		$this->writeAttribute("email", $pEmail);
		if(isset($pFullName)) {
			$this->text($pFullName);
		}
		$this->endElement();
	}
	
	public function startBug($pId) {
		if($this->bugIsOpen) {
			$this->debug("Starting a new bug despite the previous one not seeming to be closed; did you call endBug() first?");
		}
		$this->startElementNS(NULL, "bug", LpXmlWriter::$xmlNs);
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
		$this->writeStuff($pTagName, LpXmlWriter::getBool($pBoolean));
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
		$this->writeStuff("datecreated", str_replace('+00:00', 'Z', gmdate('c', $pTimestamp)));
	}
	
	public function writeNickname($pNickname) {
		$this->writeStuff("nickname", LpXmlWriter::getLpName($pNickname));
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
		$this->writeStuff("milestone", LpXmlWriter::getLpName($pMilestone));
	}
	
	
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
		$this->writeElement($pTagName, $pStuff);
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