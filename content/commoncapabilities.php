<?php

if (!isset($_REQUEST["endpoints"]))
	badrequest("no endponits specified");
if (!is_array($_REQUEST["endpoints"]))
	badrequest("endpoints is not an array");

$names = array();
foreach (Endpoint::commoncapabilities(Endpoint::load($_REQUEST["endpoints"], true)) as $cap)
	$names[] = htmlspecialchars($cap->name());

ok(json_encode(array("capabilities" => $names)), "application/json");

?>
