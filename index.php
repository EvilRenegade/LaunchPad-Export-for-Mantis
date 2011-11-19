<?php
require_once("lpxmlwriter.php");
header("Content-type: text/xml");

$lp = new lpXmlWriter("php://output");

$lp->startBug(42);
$lp->writePerson();
$lp->endBug();

unset($lp);

lpXmlWriter::getResult();