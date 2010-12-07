<?php

if (!isset($_REQUEST["id"]))
	badrequest("no endpoint ID given");
if (!Endpoint::exists($_REQUEST["id"], true))
	badrequest("no endpoint with the given ID is saved");

$endpoint = Endpoint::load($_REQUEST["id"], true);

$title = "Edit endpoint '" . $endpoint->name() . "'";
include "htmlheader.php";
?>

<h2><?php echo htmlspecialchars($title); ?></h2>

<?php
include "htmlfooter.php";
?>
