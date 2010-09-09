<?php

if (!isset($_REQUEST["id"]))
	badrequest("No collection ID specified");

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
