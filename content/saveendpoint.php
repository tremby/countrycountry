<?php

if (!isset($_SESSION["pendingendpoint"]))
	badrequest("no pending endpoint is in session data");

if ($_SESSION["pendingendpoint"]->save()) {
	flash("The new endpoint \"" . $_SESSION["pendingendpoint"]->name() . "\" has been saved");
	redirect("endpointadmin");
}

servererror("failed to save endpoint");

?>
