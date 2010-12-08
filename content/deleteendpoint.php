<?php

if (!isset($_POST["id"]))
	badrequest("No endpoint ID specified");

$errors = array();
$endpoint = Endpoint::load($_POST["id"], true) or badrequest("No endpoint with the given ID exists");
$endpoint->delete() or servererror("failed to delete endpoint '" . $endpoint->url() . "'");

ok(json_encode(array("id" => $_POST["id"])), "application/json");

?>
