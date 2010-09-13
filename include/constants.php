<?php

// filesystem path to the root directory -- one level above this file, ending in 
// a trailing slash
define("SITEROOT_LOCAL", dirname(dirname(__FILE__)) . "/");

// query path to the site root directory ending in a trailing slash -- makes an 
// absolute URL to the main page
define("SITEROOT_WEB", str_replace("//", "/", dirname($_SERVER["SCRIPT_NAME"]) . "/"));

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
	"dbpedia-owl" => "http://dbpedia.org/ontology/",
	"owl" => "http://www.w3.org/2002/07/owl#",
	"rdfs" => "http://www.w3.org/2000/01/rdf-schema#",
);

// Jamendo Sparql endpoint address
define("ENDPOINT_JAMENDO", "http://dbtune.org/jamendo/sparql/");

// results endpoint address
define("ENDPOINT_RESULTS", "http://results.nema.ecs.soton.ac.uk:8000/sparql/");

// audiofile repository endpoint address
define("ENDPOINT_REPOSITORY", "http://repository.nema.ecs.soton.ac.uk:7000/sparql/");

// dbpedia endpoint
define("ENDPOINT_DBPEDIA", "http://dbpedia.org/sparql/");

// geonames endpoint
define("ENDPOINT_GEONAMES", "http://geonames.nema.ecs.soton.ac.uk:9000/sparql/");

// BBC data endpoint (note no slash at the end of this one)
define("ENDPOINT_BBC", "http://api.talis.com/stores/bbc-backstage/services/sparql");
