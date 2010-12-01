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

// given an endpoint URL, probe it to find out what comparisons we can do on its 
// music data
function exploreendpoint($endpointurl, &$errors) {
	$capabilities = array();

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

	// things we really need

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
	if (!empty($result))
		$capabilities[] = "relationships";

	// artist name
	$result = sparqlquery($endpointurl, prefix(array("mo", "foaf")) . "
		SELECT * WHERE {
			?artist
				a mo:MusicArtist ;
				foaf:name ?artistname .
		}
		LIMIT 1
	");
	if (!empty($result))
		$capabilities[] = "artistname";

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
		$capabilities[] = "recordname";

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
		$capabilities[] = "trackname";

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
		$capabilities[] = "artistcountry";

	// date of some kind
	$result = sparqlquery($endpointurl, prefix(array("mo", "dc")) . "
		SELECT * WHERE {
			{
				?record a mo:Record .
				{ ?record dc:date ?recorddate . } UNION { ?record dc:created ?recorddate }
			} UNION {
				?track a mo:Track .
				{ ?track dc:date ?trackdate . } UNION { ?track dc:created ?trackdate }
			}
		}
		LIMIT 1
	");
	if (!empty($result))
		$capabilities[] = "recorddate";

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
		$capabilities[] = "recordtag";

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
		$capabilities[] = "tracknumber";

	// avaliable_as (could be MP3, could be something else)
	$availableas = sparqlquery($endpointurl, prefix(array("mo")) . "
		SELECT * WHERE {
			?track
				a mo:Track ;
				mo:available_as ?manifestation .
		}
		LIMIT 1
	");
	if (!empty($availableas))
		$capabilities[] = "availableas";

	// grounding
	// this will be available if a mo:Track is mo:available_as a mo:AudioFile or 
	// the first result for mo:Track mo:avaliable_as is a URL and resolves to an 
	// MP3
	$result = sparqlquery($endpointurl, prefix(array("mo")) . "
		SELECT * WHERE {
			?track
				a mo:Track ;
				mo:available_as ?audiofile .
			?audiofile
				a mo:AudioFile .
		}
		LIMIT 1
	");
	if (!empty($result) || !empty($availableas) && preg_match('%^https?://%', (string) $availableas[0]["manifestation"]) && ismp3((string) $availableas[0]["manifestation"]))
		$capabilities[] = "audiofile";

	return $capabilities;
}

if (isset($_REQUEST["endpointurl"])) {
	$errors = array();
	$capabilities = exploreendpoint($_REQUEST["endpointurl"], $errors);
	if (empty($errors)) {
		foreach ($capabilities as $cap)
			echo "<li>$cap</li>";
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
