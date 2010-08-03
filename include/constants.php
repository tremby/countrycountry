<?php

// filesystem path to the eqiat root directory -- one level above this file, 
// ending in a trailing slash
define("SITEROOT_LOCAL", dirname(dirname(__FILE__)) . "/");

// query path to the site root directory ending in a trailing slash -- makes an 
// absolute URL to the main page
define("SITEROOT_WEB", "/countrycountry/");

// useful namespaces
$ns = array(
	"geo" => "http://www.geonames.org/ontology#",
	"foaf" => "http://xmlns.com/foaf/0.1/",
	"mo" => "http://purl.org/ontology/mo/",
	"tags" => "http://www.holygoat.co.uk/owl/redwood/0.1/tags/",
	"dc" => "http://purl.org/dc/elements/1.1/",
	"xsd" => "http://www.w3.org/2001/XMLSchema#",
	"rdf" => "http://www.w3.org/1999/02/22-rdf-syntax-ns#",
	"pv" => "http://purl.org/net/provenance/ns#",
	"ore" => "http://www.openarchives.org/ore/terms/",
	"sim" => "http://purl.org/ontology/similarity/",
	"off" => "http://purl.org/ontology/off/",
);

// Jamendo Sparql endpoint address
define("ENDPOINT_JAMENDO", "http://dbtune.org/jamendo/sparql/");

// results endpoint address
define("ENDPOINT_RESULTS", "http://results.nema.ecs.soton.ac.uk:8000/sparql/");

// audiofile repository endpoint address
define("ENDPOINT_REPOSITORY", "http://lslvm-bjn1.ecs.soton.ac.uk:8080/sparql/");
