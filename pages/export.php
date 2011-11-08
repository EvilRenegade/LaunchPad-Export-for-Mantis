<?php
	// This function was designed by Martijn ? >> http://php.dijksterhuis.org/fixing-html_entities-for-xml/
	// Thanks for that!
	// MODIFIED for: sbquo, lsquo, &rsquo;, &infin;, larr, rarr, &ndash;
	function xmlentities($string)
	{
	
		// Convert HTML entities to XML
		// http://techtrouts.com/webkit-entity-nbsp-not-defined-convert-html-entities-to-xml/
	   
		$htmlentities = array( "&quot;","&amp;","&lt;","&gt;","&nbsp;","&iexcl;","&cent;","&pound;","&curren;","&yen;","&brvbar;","&sect;","&uml;","&copy;","&ordf;","&laquo;","&not;","&shy;","&reg;","&macr;","&deg;","&plusmn;","&sup2;","&sup3;","&acute;","&micro;","&para;","&middot;","&cedil;","&sup1;","&ordm;","&raquo;","&frac14;","&frac12;","&frac34;","&iquest;","&Agrave;","&Aacute;","&Acirc;","&Atilde;","&Auml;","&Aring;","&AElig;","&Ccedil;","&Egrave;","&Eacute;","&Ecirc;","&Euml;","&Igrave;","&Iacute;","&Icirc;","&Iuml;","&ETH;","&Ntilde;","&Ograve;","&Oacute;","&Ocirc;","&Otilde;","&Ouml;","&times;","&Oslash;","&Ugrave;","&Uacute;","&Ucirc;","&Uuml;","&Yacute;","&THORN;","&szlig;","&agrave;","&aacute;","&acirc;","&atilde;","&auml;","&aring;","&aelig;","&ccedil;","&egrave;","&eacute;","&ecirc;","&euml;","&igrave;","&iacute;","&icirc;","&iuml;","&eth;","&ntilde;","&ograve;","&oacute;","&ocirc;","&otilde;","&ouml;","&divide;","&oslash;","&ugrave;","&uacute;","&ucirc;","&uuml;","&yacute;","&thorn;","&yuml;","&euro;", "&sbquo;", "&lsquo;", "&rsquo;", "&infin;", "&larr;", "&rarr;", "&ndash;");
	
		$xmlentities = array("&#34;","&#38;","&#60;","&#62;","&#160;","&#161;","&#162;","&#163;","&#164;","&#165;","&#166;","&#167;","&#168;","&#169;","&#170;","&#171;","&#172;","&#173;","&#174;","&#175;","&#176;","&#177;","&#178;","&#179;","&#180;","&#181;","&#182;","&#183;","&#184;","&#185;","&#186;","&#187;","&#188;","&#189;","&#190;","&#191;","&#192;","&#193;","&#194;","&#195;","&#196;","&#197;","&#198;","&#199;","&#200;","&#201;","&#202;","&#203;","&#204;","&#205;","&#206;","&#207;","&#208;","&#209;","&#210;","&#211;","&#212;","&#213;","&#214;","&#215;","&#216;","&#217;","&#218;","&#219;","&#220;","&#221;","&#222;","&#223;","&#224;","&#225;","&#226;","&#227;","&#228;","&#229;","&#230;","&#231;","&#232;","&#233;","&#234;","&#235;","&#236;","&#237;","&#238;","&#239;","&#240;","&#241;","&#242;","&#243;","&#244;","&#245;","&#246;","&#247;","&#248;","&#249;","&#250;","&#251;","&#252;","&#253;","&#254;","&#255;","&#8364;", "&#130;", "&#145;", "&#146;", "&#8734;", "&#8592;", "&#8594;", "&#150;");
	   
		// HTML entities are case-sensitive (http://htmlhelp.com/reference/html40/entities/)
		return str_replace($htmlentities,$xmlentities,$string);
	}
	
	// LP is a bit conservative/pesky about allowed characters in usernames: [a-z0-9][a-z0-9\+\.\-]* is the only allowed import format
	// we'll just replace all illegal characters with a dash
	function getLpName($pUsername) {
		$retVal = preg_replace('/[^a-z0-9\+\.\-]/', '-', strtolower($pUsername));
		while(!ctype_alnum($retVal[0]) && (strlen($retVal) > 0)) {
			$retVal = ltrim($retVal, $retVal[0]);
		}
		
		return $retVal;
	}

	function getPersonXml($pUserId, $pTagName = 'person') {
		if(user_exists($pUserId)) {
			// there was a bug where we'd get empty e-mail attributes, this should fix that.
			$email = user_get_email($pUserId);
			$emailbit = (($email !== null) && !is_blank($email)) ? " email=\"$email\"" : '';
			return sprintf('<%s%s>%s</%s>', $pTagName, $emailbit, xmlentities(htmlentities(user_get_name($pUserId), ENT_IGNORE, 'UTF-8')), $pTagName);
		} else {
			return "<$pTagName name=\"nobody\"/>";
		}
	}
	
	// based on https://help.launchpad.net/Bugs/Statuses/External#Mantis
	function getLaunchpadStatus($pMantisStatus, $pMantisResolution, $pMantisReproducibility) {
		$retVal = '';
		
		if(($pMantisStatus == RESOLVED) || ($pMantisStatus == CLOSED)) {
			switch($pMantisResolution) {
				case REOPENED:
					$retVal = 'NEW';
					break;
				
				case OPEN:
				case FIXED:
				case NOT_A_BUG:
					$retVal = 'FIXRELEASED';
					break;
				
				case UNABLE_TO_DUPLICATE:
				case NOT_FIXABLE:
				case DUPLICATE:
				case SUSPENDED:
					$retVal = 'INVALID';
					break;
				
				case WONT_FIX:
					$retVal = 'WONTFIX';
					break;
				
				default:
					$retVal = 'UNKNOWN';
					break;
			}
		} else {
			switch($pMantisStatus) {
				case NEW_:
					$retVal = 'NEW';
					break;
				
				case FEEDBACK:
					$retVal = 'INCOMPLETE';
					break;
				
				case ACKNOWLEDGED: // LP's external connector marks these confirmed, but since they're not, we're converting differently
					$retVal = 'UNKNOWN';
					break;
				
				case CONFIRMED:
					$retVal = 'CONFIRMED';
					break;
				
				case ASSIGNED:
					$retVal = 'INPROGRESS';
					break;
				
				default:
					$retVal = 'UNKNOWN';
					break;
			}
			
			switch($pMantisReproducibility) {
				case REPRODUCIBILITY_HAVENOTTRIED: // this is surely debatable, but since I'm writing this for my own purposes first, this is how we'll handle it :P
					$retVal = 'INCOMPLETE';
					break;
				
				case REPRODUCIBILITY_UNABLETODUPLICATE:
					$retVal = 'INVALID';
					break;
			}
		}

		return $retVal;
	}
	
	function getImportance($pPriority, $pSeverity) {
		$retVal = '';
		
		switch($pPriority) {
			case NONE:
				$retVal = 'WISHLIST'; // could also be LOW, I'm thinking "If we had infinite time and infinite resources, I'd also like to fix..." here
				break;
			
			case LOW:
				$retVal = 'LOW';
				break;
			
			case NORMAL:
				$retVal = 'MEDIUM';
				break;
			
			case HIGH:
				$retVal = 'HIGH';
				break;
			
			case URGENT:
			case IMMEDIATE:
				$retVal = 'CRITICAL';
				break;
			
			default:
				$retVal = 'UNDECIDED';
				break;
		}
		
		switch($pSeverity) {
			case FEATURE:
				$retVal = 'WISHLIST';
				break;
			
			case BLOCK:
				$retVal = 'CRITICAL';
				break;
		}
		
		return $retVal;
	}
	
	function getMilestone($pTargetVersion, $pFixedInVersion) {
		$curProjName = project_get_name(helper_get_current_project());
		if(!is_blank($pTargetVersion)) {
			return sprintf('<milestone>%s</milestone>', getLpName(str_replace("$curProjName ", '', $pTargetVersion))); // LaunchPad auto-prepends the project name.
		} else if(!is_blank($pFixedInVersion)) {
			return sprintf('<milestone>%s</milestone>', getLpName(str_replace("$curProjName ", '', $pFixedInVersion)));
		} else {
			return '';
		}
	}
	
	function getTags($pBugId) {
		$tags = tag_bug_get_attached($pBugId);
		if(count($tags) != 0) {
			$tagXml = "<tags>\n";
			foreach($tags as $tag) {
				$tagName = getLpName($tag[name]);
				if(strlen($tagName) > 0) {
					$tagXml .= "\t\t\t<tag>$tagName</tag>\n";
				}
			}
			$tagXml .= "\t\t</tags>";
			return $tagXml;
		} else {
			return '';
		}
	}
	
	function getSubscribers($pBugId) {
		$watchers = bug_get_monitors($pBugId);
		if(count($watchers) > 0) {
			$retVal = "<subscriptions>\n";
			foreach($watchers as $watcher) {
				$retVal .= "\t\t\t" . getPersonXml($watcher, 'subscriber') . "\n";
			}
			$retVal .= "\t\t</subscriptions>";
			return $retVal;
		}
	}
	
	function getUserContent($pBugId) {
		/* 	WARNING this copies private bugnotes as well!! Change the query in $dbQuery if you don't want your privates to show!
			Mantis associates attachments with the issue, whereas LP associates attachments with comments.
			There would be a variety of ways to map attachments to comments, but ultimately, retroactive commentification just wasn't important enough:
			The current solution simply builds a timeline of comments and attachments and inserts pseudo-comments for attachments.
			Yes, this is a cop-out, but it's a "good enough" solution.
			
			The architecture of this function mirrors bugnote_get_all_bugnotes() from Mantis core. Talk to the Mantis devs if you take issue with it. ;)
		*/
		$dbQuery = "SELECT * FROM (
  SELECT  u.username AS user, bn.reporter_id AS uid, bt.note AS text, NULL AS file_name, NULL AS file_type, NULL AS file_content, bn.date_submitted AS creation_date, DATE_FORMAT(CONVERT_TZ(FROM_UNIXTIME( bn.date_submitted), '+01:00', '+00:00'), '%Y-%m-%dT%TZ') AS date_string FROM mantis_bugnote_table bn
  INNER JOIN mantis_bugnote_text_table bt ON bn.bugnote_text_id = bt.id
  INNER JOIN mantis_user_table u ON bn.reporter_id = u.id
  WHERE bug_id = " . db_param() . "
  UNION
  SELECT  u.username AS user, bf.user_id AS uid, CONCAT(u.username, ' uploaded ', bf.filename, '.') AS text, bf.filename AS file_name, bf.file_type AS file_type, bf.content AS file_content, bf.date_added AS creation_date, DATE_FORMAT(CONVERT_TZ(FROM_UNIXTIME(bf.date_added), '+01:00', '+00:00'), '%Y-%m-%dT%TZ') AS date_string FROM mantis_bug_file_table bf
  INNER JOIN mantis_user_table u ON bf.user_id = u.id
  WHERE bug_id = " . db_param() . "
) screw_you
ORDER BY creation_date";
		$result = db_query_bound($dbQuery, array($pBugId, $pBugId));
		$items = array();
		while($row = db_fetch_array($result)) {
			$sender = user_is_anonymous($row['uid']) ? '<sender name="nobody"/>' : getPersonXml($row['uid'], 'sender');
			if(is_null($row['file_name'])) { // this is a normal comment
				$items[] = sprintf("\t\t<comment>
\t\t\t%s
\t\t\t<date>%s</date>
\t\t\t<text>%s</text>
\t\t</comment>",	$sender,
					$row['date_string'],
					xmlentities(htmlentities($row['text'], ENT_IGNORE, 'UTF-8')));	
			} else { // this is an attachment dummy comment
				$items[] = sprintf("\t\t<comment>
\t\t\t%s
\t\t\t<date>%s</date>
\t\t\t<text>%s</text>
\t\t\t<attachment>
\t\t\t\t<type>%s</type>
\t\t\t\t<filename>%s</filename>
\t\t\t\t<mimetype>%s</mimetype>
\t\t\t\t<contents>%s</contents>
\t\t\t</attachment>
\t\t</comment>",	$sender,
					$row['date_string'],
					xmlentities(htmlentities($row['text'], ENT_IGNORE, 'UTF-8')),
					(stripos($row['file_name'], 'patch') !== false) ? "PATCH" : "UNSPECIFIED",
					xmlentities(htmlentities($row['file_name'], ENT_IGNORE, 'UTF-8')),
					$row['file_type'],
					base64_encode($row['file_content'])
					);	
			}
			
		}
		return implode(PHP_EOL, $items);
	}

	function getBugXml($pBug) {
		$isBugPrivate = ($pBug->view_state == VS_PRIVATE);
		//$isBugSecurityRelated = 'False'; // stock Mantis does not have an equivalent for this
		$duplicateOf = null; // needs to be filled
		$dateCreated = str_replace('+00:00', 'Z', gmdate('c', $pBug->date_submitted));
		$summaryTitle = $pBug->summary;
		$description = $pBug->__get('description') . '
		
		Steps to Reproduce:
		' . $pBug->__get('steps_to_reproduce') . '
		
		Additional Information:
		' . $pBug->__get('additional_information');

		$bugXml = sprintf(
"\t<bug xmlns=\"https://launchpad.net/xmlns/2006/bugs\" id=\"%u\">
\t\t<private>%s</private>
\t\t<datecreated>%s</datecreated>
\t\t<title>%s</title>
\t\t<description>%s</description>
\t\t%s
\t\t<status>%s</status>
\t\t<importance>%s</importance>
\t\t%s
\t\t%s
\t\t%s
\t\t<bugwatches>
\t\t\t<bugwatch href=\"%sview.php?id=%u\" />
\t\t</bugwatches>
\t\t%s
\t\t<comment>
\t\t\t%s
\t\t\t<date>%s</date>
\t\t\t<title>%s</title>
\t\t\t<text>%s</text>
\t\t</comment>
%s
\t</bug>",
			$pBug->id,
			$isBugPrivate ? 'True' : 'False',
			$dateCreated,
			xmlentities(htmlentities($summaryTitle, ENT_IGNORE, 'UTF-8')),
			xmlentities(htmlentities($description, ENT_IGNORE, 'UTF-8')),
			getPersonXml($pBug->reporter_id, 'reporter'),
			getLaunchpadStatus($pBug->status, $pBug->resolution, $pBug->reproducibility),
			getImportance($pBug->priority, $pBug->severity),
			getMilestone($pBug->target_version, $pBug->fixed_in_version),
			getPersonXml($pBug->handler_id, 'assignee'),
			getTags($pBug->id),
			config_get('path'), $pBug->id,
			getSubscribers($pBug->id),
			getPersonXml($pBug->reporter_id, 'sender'),
			$dateCreated,
			xmlentities(htmlentities($summaryTitle, ENT_IGNORE, 'UTF-8')),
			xmlentities(htmlentities($description, ENT_IGNORE, 'UTF-8')),
			getUserContent($pBug->id)
		);
		
		// bug_get_attachments( $p_bug_id )
		return $bugXml;
	}
	
	if(!current_user_is_administrator()) {
		die("Not logged in as an administrator; aborting.");
	}

	html_page_top('LaunchPad Export Plugin');
	$pageNumber = 1;
	$bugAmount = -1;
	$pageCount = 0;
	$bugCount = 0;
	$bugs = filter_get_bug_rows($pageNumber, $bugAmount, $pageCount, $bugCount);
	echo '<h1>Exporting '.$bugCount.' bugs</h1>';
	$fileName = project_get_name(helper_get_current_project()) . '-LaunchPad-Import-' . date('Ymd-His') . ".xml";
	// Output happens here
	$outputFile = fopen("./uploads/$fileName", 'w');
	
	fwrite($outputFile,'<?xml version="1.0"?>
<launchpad-bugs xmlns="https://launchpad.net/xmlns/2006/bugs">
');
	foreach($bugs as $bug) {
		fwrite($outputFile, getBugXml($bug) . "\n");
	}
	fwrite($outputFile, '</launchpad-bugs>');
	
	fclose($outputFile);
	// Output done
	echo 'Output done.';
	html_page_bottom();