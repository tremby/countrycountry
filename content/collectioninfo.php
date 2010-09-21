<?php

if (!isset($_REQUEST["uri"]))
	badrequest("URI not specified");

$errors = array();
$collection = getcollectioninfo($_REQUEST["uri"], $errors);

if ($collection === false) {
	array_unshift($errors, "Couldn't get collection info. RDF parser errors follow.");
	servererror($errors);
}

$aggregate = $collection["index"][$collection["uri"] . "#aggregate"];

ok(json_encode(array(
	"title" => $aggregate[$ns["dc"] . "title"][0],
	"creator" => $aggregate[$ns["dc"] . "creator"][0],
	"modified" => $collection["modified"],
	"description" => isset($aggregate[$ns["dc"] . "description"]) ? $aggregate[$ns["dc"] . "description"][0] : null,
	"signalcount" => count($aggregate[$ns["ore"] . "aggregates"]),
	"uri" => $collection["uri"],
)), "application/json");

?>
