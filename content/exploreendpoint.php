<?php

require_once SITEROOT_LOCAL . "include/arc/ARC2.php";
require_once SITEROOT_LOCAL . "include/Graphite.php";

// return true if the URL given definitely resolves to an MP3
// (so if it's protected (401) return false)
function ismp3($url) {
	$fp = fopen($url, "r");
	if (!$fp)
		return false;
	$md = stream_get_meta_data($fp);
	fclose($fp);
	foreach ($md["wrapper_data"] as $header)
		if (preg_match('%^Content-Type: audio/mpeg%i', $header))
			return true;
	return false;
}

// expand a shortened URI
function expanduri($uri) {
	if (strpos($uri, ":") === false)
		return $uri;
	list($prefix, $suffix) = explode(":", $uri);
	if (!array_key_exists($prefix, $GLOBALS["ns"])) {
		trigger_error("prefix $prefix (of $prefix:$suffix) doesn't exist in global ns array", E_USER_WARNING);
		return $uri;
	}
	return $GLOBALS["ns"][$prefix] . $suffix;
}

// hack to make a triple array suitable for importing to Graphite from a Sparql 
// result
// if $s, $p or $o contain a : they are expanded with the global $ns variable to 
// a URI, otherwise treated as array keys to find in $resultarray
function sparqlresulttotriple($s, $p, $o, $resultarray = null) {
	$triple = array();

	foreach (array("s" => $s, "p" => $p, "o" => $o) as $letter => $value) {
		if (strpos($value, ":") !== false) {
			$triple[$letter] = expanduri($value);
			continue;
		}

		if (!is_array($resultarray)) {
			trigger_error("expected a fourth parameter if using identifiers for s, p or o", E_USER_ERROR);
			exit;
		}

		$triple[$letter] = $resultarray[$value];
		if (isset($resultarray["$value type"]))
			$triple[$letter . "_type"] = $resultarray["$value type"];
		if (isset($resultarray["$value datatype"]))
			$triple[$letter . "_datatype"] = $resultarray["$value datatype"];
		if (isset($resultarray["$value lang"]))
			$triple[$letter . "_lang"] = $resultarray["$value lang"];
	}

	return $triple;
}

// given an endpoint URL, probe it to find out what comparisons we can do on its 
// music data
function exploreendpoint($endpointurl, &$errors) {
	$capabilitytriples = array();

	// basic checks
	if (empty($endpointurl)) {
		$errors[] = "No endpoint URL given";
		return null;
	}
	if (!preg_match('%^https?://%', $endpointurl)) {
		$errors[] = "Couldn't parse endpoint URL";
		return null;
	}

	// set up Arc
	$config = array(
		"remote_store_endpoint" => $endpointurl,
		"reader_timeut" => 20,
		"ns" => $GLOBALS["ns"],
	);
	$store = ARC2::getRemoteStore($config);

	// try a test query
	$result = $store->query("SELECT ?s ?p ?o WHERE { ?s ?p ?o . } LIMIT 1", "rows");

	if (count($store->getErrors())) {
		$errors[] = "Problem communicating with endpoint. Arc's errors follow.";
		$errors = array_merge($errors, $store->getErrors());
		return null;
	}

	if (empty($result)) {
		$errors[] = "No triples were returned -- verify the endpoint URL is correct";
		return null;
	}

	// check that mo:MusicArtist, mo:Record, mo:Track and mo:Signal exist and 
	// are joined with foaf:maker, mo:track and mo:published_as
	$result = sparqlquery($endpointurl, prefix(array("mo", "foaf")) . "
		SELECT * WHERE {
			?artist
				a mo:MusicArtist .
			?record
				a mo:Record ;
				foaf:maker ?artist ;
				mo:track ?track .
			?track
				a mo:Track .
			?signal
				a mo:Signal ;
				mo:published_as ?track .
		}
		LIMIT 1
	");
	if (!empty($result)) {
		$capabilitytriples["relationships"] = array(
			sparqlresulttotriple("artist", "rdf:type", "mo:MusicArtist", $result[0]),
			sparqlresulttotriple("record", "rdf:type", "mo:Record", $result[0]),
			sparqlresulttotriple("record", "foaf:maker", "artist", $result[0]),
			sparqlresulttotriple("record", "mo:track", "track", $result[0]),
			sparqlresulttotriple("track", "rdf:type", "mo:Track", $result[0]),
			sparqlresulttotriple("signal", "rdf:type", "mo:Signal", $result[0]),
			sparqlresulttotriple("signal", "mo:published_as", "track", $result[0]),
		);
	}

	// artist name
	$result = sparqlquery($endpointurl, prefix(array("mo", "foaf")) . "
		SELECT * WHERE {
			?artist
				a mo:MusicArtist ;
				foaf:name ?artistname .
		}
		LIMIT 1
	");
	if (!empty($result)) {
		$capabilitytriples["artistname"] = array(
			sparqlresulttotriple("artist", "rdf:type", "mo:MusicArtist", $result[0]),
			sparqlresulttotriple("artist", "foaf:name", "artistname", $result[0]),
		);
	}

	// record name
	$result = sparqlquery($endpointurl, prefix(array("mo", "dc")) . "
		SELECT * WHERE {
			?record
				a mo:Record ;
				dc:title ?recordname .
		}
		LIMIT 1
	");
	if (!empty($result))
		$capabilitytriples["recordname"] = array(
			sparqlresulttotriple("record", "rdf:type", "mo:Record", $result[0]),
			sparqlresulttotriple("record", "dc:title", "recordname", $result[0]),
		);

	// track name
	$result = sparqlquery($endpointurl, prefix(array("mo", "dc")) . "
		SELECT * WHERE {
			?track
				a mo:Track ;
				dc:title ?trackname .
		}
		LIMIT 1
	");
	if (!empty($result))
		$capabilitytriples["trackname"] = array(
			sparqlresulttotriple("track", "rdf:type", "mo:Track", $result[0]),
			sparqlresulttotriple("track", "dc:title", "trackname", $result[0]),
		);

	// artist country
	$result = sparqlquery($endpointurl, prefix(array("mo", "foaf", "geo")) . "
		SELECT * WHERE {
			?artist
				a mo:MusicArtist ;
				foaf:based_near ?basednear .
			?basednear geo:inCountry ?country .
		}
		LIMIT 1
	");
	if (!empty($result))
		$capabilitytriples["artistcountry"] = array(
			sparqlresulttotriple("artist", "rdf:type", "mo:MusicArtist", $result[0]),
			sparqlresulttotriple("artist", "foaf:based_near", "basednear", $result[0]),
			sparqlresulttotriple("basednear", "geo:inCountry", "country", $result[0]),
		);

	// date of some kind
	$result = sparqlquery($endpointurl, prefix(array("mo", "dc")) . "
		SELECT * WHERE {
			{
				?record a mo:Record .
				{ ?record dc:date ?recorddate . } UNION { ?record dc:created ?recordcreated }
			} UNION {
				?track a mo:Track .
				{ ?track dc:date ?trackdate . } UNION { ?track dc:created ?trackcreated }
			}
		}
		LIMIT 1
	");

	if (!empty($result)) {
		if (isset($result[0]["track"]))
			$capabilitytriples["recorddate"] = array(
				sparqlresulttotriple("track", "rdf:type", "mo:Track", $result[0]),
				isset($result[0]["trackdate"]) ? sparqlresulttotriple("track", "dc:date", "trackdate", $result[0]) : sparqlresulttotriple("track", "dc:created", "trackcreated", $result[0]),
			);
		else
			$capabilitytriples["recorddate"] = array(
				sparqlresulttotriple("record", "rdf:type", "mo:Record", $result[0]),
				isset($result[0]["recorddate"]) ? sparqlresulttotriple("record", "dc:date", "recorddate", $result[0]) : sparqlresulttotriple("record", "dc:created", "recordcreated", $result[0]),
			);
	}

	// record tag
	$result = sparqlquery($endpointurl, prefix(array("mo", "tags")) . "
		SELECT * WHERE {
			?record
				a mo:Record ;
				tags:taggedWithTag ?tag .
		}
		LIMIT 1
	");
	if (!empty($result))
		$capabilitytriples["recordtag"] = array(
			sparqlresulttotriple("record", "rdf:type", "mo:Record", $result[0]),
			sparqlresulttotriple("record", "tags:taggedWithTag", "tag", $result[0]),
		);

	// track number
	$result = sparqlquery($endpointurl, prefix(array("mo")) . "
		SELECT * WHERE {
			?track
				a mo:Track ;
				mo:track_number ?tracknumber .
		}
		LIMIT 1
	");
	if (!empty($result))
		$capabilitytriples["tracknumber"] = array(
			sparqlresulttotriple("track", "rdf:type", "mo:Track", $result[0]),
			sparqlresulttotriple("track", "mo:track_number", "tracknumber", $result[0]),
		);

	// avaliable_as (could be MP3, could be something else)
	// if we get an MP3 let's say we can ground against it too
	$result = sparqlquery($endpointurl, prefix(array("mo")) . "
		SELECT * WHERE {
			?track
				a mo:Track ;
				mo:available_as ?manifestation .
		}
		LIMIT 1
	");
	if (!empty($result)) {
		$capabilitytriples["availableas"] = array(
			sparqlresulttotriple("track", "rdf:type", "mo:Track", $result[0]),
			sparqlresulttotriple("track", "mo:available_as", "manifestation", $result[0]),
		);
		if (preg_match('%^https?://%', (string) $result[0]["manifestation"]) && ismp3((string) $result[0]["manifestation"]))
			$capabilitytriples["grounding"] = array(); // manifestation gives an MP3 -- we can try to ground on this
	}

	// grounding (proper grounding)
	$result = sparqlquery($endpointurl, prefix(array("mo")) . "
		SELECT * WHERE {
			?track
				a mo:Track ;
				mo:available_as ?manifestation .
			?manifestation
				a mo:AudioFile .
		}
		LIMIT 1
	");
	if (!empty($result))
		$capabilitytriples["grounding"] = array(
			sparqlresulttotriple("track", "rdf:type", "mo:Track", $result[0]),
			sparqlresulttotriple("track", "mo:available_as", "manifestation", $result[0]),
			sparqlresulttotriple("manifestation", "rdf:type", "mo:AudioFile", $result[0]),
		);

	return $capabilitytriples;
}

if (isset($_REQUEST["endpointurl"])) {
	$errors = array();
	$capabilitytriples = exploreendpoint($_REQUEST["endpointurl"], $errors);
	if (empty($errors)) {
		$title = "Sparql endpoint results";
		include "htmlheader.php";
		?>
		<h1><?php echo htmlspecialchars($title); ?></h1>
		<?php foreach ($capabilitytriples as $cap => $triples) { ?>
			<h2><?php echo htmlspecialchars($cap); ?></h2>
			<h3>Example triples</h3>
			<?php
			$graph = new Graphite($GLOBALS["ns"]);
			$graph->addTriples($triples);
			echo $graph->dump();
			?>
		<?php } ?>
		<?php
		include "htmlfooter.php";
		exit;
	}
}

$title = "Explore Sparql endpoint";
include "htmlheader.php";
?>

<h1><?php echo htmlspecialchars($title); ?></h1>

<?php if (isset($errors) && !empty($errors)) { ?>
	<div class="errors">
		<h2>Errors</h2>
		<ul>
			<?php foreach ($errors as $error) { ?>
				<li><?php echo htmlspecialchars($error); ?></li>
			<?php } ?>
		</ul>
	</div>
<?php } ?>

<p>We expect to find objects from the <a href="http://musicontology.com/">Music Ontology</a> such as mo:Signal, mo:Track, mo:Record, mo:MusicArtist and, for grounding, mo:AudioFile.</p>
<p>TODO: graph of the kind of stuff we expect</p>

<form action="<?php echo SITEROOT_WEB; ?>exploreendpoint" method="get">
	<dl>
		<dt><label for="endpointurl">Endpoint URL</label></dt>
		<dd>
			<input type="text" name="endpointurl" id="endpointurl" size="64"<?php if (isset($_REQUEST["endpointurl"])) { ?> value="<?php echo htmlspecialchars($_REQUEST["endpointurl"]); ?>"<?php } ?>>
			<span class="hint">Some endpoints are sensitive about whether or not they have a trailing slash</span>
		</dd>

		<dt>Test endpoint</dt>
		<dd>
			<input type="submit" name="testendpoint" value="Test endpoint">
		</dd>
	</dl>
</form>
<?php include "htmlfooter.php"; ?>
