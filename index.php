<?php
require_once("lpxmlwriter.php");
header("Content-type: text/xml");

$lp = new LpXmlWriter("php://output");

$lp->startBug(42);
$lp->writePerson();
$lp->endBug();

unset($lp);

LpXmlWriter::getResult();