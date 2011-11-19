<?php
require_once("lpxmlwriter.php");
header("Content-type: text/xml");

$lp = new lpXmlWriter("php://output");

// imagine your bug loop here
// gather data about your bug
$private = true;
$securityRelated = false;
$duplicateOf = 69;
$dateCreated = 1342696969;
$bugNickname = "Test Büg";
$title = "This is a test bug";
$description = "This is the bug's
<h1>TOTALLY AWESOME</h1>
description.";
$user = lpXmlWriter::gpa("Renegade", "Renegade@RenegadeProjects.com", "Evil Renegade");
$status = lpXmlWriter::$WONTFIX;
$importance = lpXmlWriter::$CRITICAL;
$milestone = "69.8008 \"Horny Hedgehog\"";
$poorBastard = lpXmlWriter::gpa("Coder~Slave", "iworkalldayandiworkallnight@example.com", "Coding Slave");
$urls = array("I have no idea" => "https://launchpad.com", "what these URLs are even for" => "https://renegadeprojects.com");
$cves = array("this ain't properly formatted, moron!", "1 neither 9 is this 99 --", "2042-6969", "or this 2011/11/19", "2069-4242", "or this, even: 201111191351");
$tags = array("Foo", "Bar", "Baz", "Die unerträgliche Leichtigkeit des Seins");
$bugWatches = array("http://grooveshark.com/s/It+s+All+About+The+Pentiums/2hchKq?src=5", "https://www.youtube.com/watch?v=Fow7iUaKrq4");
$subscribers = array($user, lpXmlWriter::gpa("Lady Gaga", "hotstefani86@ladygaga.com", "Gaga OOh-La-La"), lpXmlWriter::getPersonArray("Lexa", "Rommie@Andromeda-Ascendant.mil", "Alexandra L. Doig"));
$comments = array(
	array(
		"sender" => $user,
		"date" => $dateCreated,
		"title" => $title,
		"text" => $description
	),
	array(
		"sender" => lpXmlWriter::gpa("Lady Gaga", "hotstefani86@ladygaga.com", "Gaga OOh-La-La"),
		"date" => 1234567890,
		"text" => "Here are the pictures you wanted!",
		"attachments" => array(
			array(
				"contents" => "This is not an image.",
				"url" => "http://thisleadsnowhere.example",
				"patch" => false,
				"filename" => "poke_er_face_1.jpg",
				"title" => "Poke 'er Face I",
				"mimetype" => "application/pr0nz"
			),
			array(
				"contents" => "This attachments contains barely more than its contents.",
				"filename" => "poke_er_face_1.jpg"
			)
		)
	),
	array(
		"date" => 1234567891,
		"text" => "Oh baby, show me more!",
		"attachments" => array(
			array(
				"contents" => "I wish this were an image.",
				"patch" => true,
				"filename" => "anon_me_hard.jpg",
				"mimetype" => "application/pr0nz"
			)
		)
	)
);


// write your bug data
$lp->startBug(42);
$lp->writePrivate($private);
$lp->writeSecurityRelated($securityRelated);
$lp->writeDuplicateOf($duplicateOf);
$lp->writeDateCreated($dateCreated);
$lp->writeNickname($bugNickname);
$lp->writeTitle($title);
$lp->writeDescription($description);
$lp->writePersonA("reporter", $user);
$lp->writeStatus($status);
$lp->writeImportance($importance);
$lp->writeMilestone($milestone);
$lp->writePersonA("assignee", $poorBastard);
$lp->writeUrls($urls);
$lp->writeCves($cves);
$lp->writeTags($tags);
$lp->writeBugwatches($bugWatches);
$lp->writeSubscriptions($subscribers);
$lp->writeComments($comments);
$lp->endBug();
// end of bug loop

unset($lp);