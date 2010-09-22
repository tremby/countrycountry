<?php

if (!isset($_REQUEST["id"]))
	badrequest("No collection ID specified");

$errors = array();
$collection = getcollectioninfo("http://collections.nema.ecs.soton.ac.uk/signalcollection/" . $_REQUEST["id"], $errors);
$aggregate = $collection["index"][$collection["uri"] . "#aggregate"];
if ($aggregate[$ns["dc"] . "creator"][0] == "bjn")
	badrequest("For demo purposes you can't delete collections by bjn.");

$filename = SITEROOT_LOCAL . "signalcollections/" . $_REQUEST["id"] . ".xml";
if (file_exists($filename) && dirname($filename) == SITEROOT_LOCAL . "signalcollections") {
	unlink($filename);
}

$dirname = SITEROOT_LOCAL . "filecollections/" . $_REQUEST["id"];
if (file_exists($dirname) && dirname($dirname) == SITEROOT_LOCAL . "filecollections") {
	rmrecursive($dirname);
}

redirect(SITEROOT_WEB . "existingcollections");

?>
