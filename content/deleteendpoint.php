<?php

if (!isset($_POST["id"]))
	badrequest("No endpoint ID specified");

$endpoint = Endpoint::load($_POST["id"], true) or badrequest("No endpoint with the given ID exists");
$success = $endpoint->delete();

if (preferredtype(array("application/json", "text/html")) == "application/json") {
	if ($success)
		ok(json_encode(array("id" => $_POST["id"])), "application/json");
	servererror("failed to delete endpoint '" . $endpoint->name() . "'");
}

flash("Endpoint '" . $endpoint->name() . "' successfully deleted");
redirect("endpointadmin");

?>
