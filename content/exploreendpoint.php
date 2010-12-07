<?php

require_once SITEROOT_LOCAL . "include/arc/ARC2.php";
require_once SITEROOT_LOCAL . "include/Graphite.php";

// given an endpoint URL, probe it to find out what comparisons we can do on its 
// music data
function exploreendpoint($endpointurl, &$errors, &$queries) {
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
	$result = sparqlquery($endpointurl, $queries[] = prefix(array("mo", "foaf")) . "
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
	$result = sparqlquery($endpointurl, $queries[] = prefix(array("mo", "foaf")) . "
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
	$result = sparqlquery($endpointurl, $queries[] = prefix(array("mo", "dc")) . "
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
	$result = sparqlquery($endpointurl, $queries[] = prefix(array("mo", "dc")) . "
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
	$result = sparqlquery($endpointurl, $queries[] = prefix(array("mo", "foaf", "geo")) . "
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
	$result = sparqlquery($endpointurl, $queries[] = prefix(array("mo", "dc")) . "
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
	$result = sparqlquery($endpointurl, $queries[] = prefix(array("mo", "tags")) . "
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
	$result = sparqlquery($endpointurl, $queries[] = prefix(array("mo")) . "
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
	$result = sparqlquery($endpointurl, $queries[] = prefix(array("mo")) . "
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
	$result = sparqlquery($endpointurl, $queries[] = prefix(array("mo")) . "
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

$capabilities = array(
	"relationships" => array(
		"title" => "Relationships between artists, records, tracks and signals are present",
		"description" => "There exist objects of types mo:MusicArtist, mo:Record, mo:Track and mo:Signal linked in such a way that their relationships can be understood.",
	),
	"artistname" => array(
		"title" => "Artist names are available",
		"description" => "Objects of type mo:MusicArtist have names available via the foaf:name predicate.",
	),
	"recordname" => array(
		"title" => "Record names are available",
		"description" => "Objects of type mo:Record have names available via the dc:title predicate.",
	),
	"trackname" => array(
		"title" => "Track names are available",
		"description" => "Objects of type mo:Track have names available via the dc:title predicate.",
	),
	"artistcountry" => array(
		"title" => "Artist country data available",
		"description" => "Artists are declared to be foaf:based_near a place and the triples which are necessary to determine which country this place is in are also present.",
	),
	"recorddate" => array(
		"title" => "Date information available",
		"description" => "Either mo:Record or mo:Track objects have date information provided by either the dc:date or dc:created predicates.",
	),
	"recordtag" => array(
		"title" => "Records are tagged",
		"description" => "Objects of type mo:Record are tagged using the tags:taggedWithTag predicate.",
	),
	"tracknumber" => array(
		"title" => "Track numbers available",
		"description" => "Objects of type mo:Track in this endpoint are linked via mo:track_number to their track numbers.",
	),
	"availableas" => array(
		"title" => "Samples available",
		"description" => "The endpoint links mo:Track objects via mo:available_as statements to other resources. These could be anything but in practice tend to be playlist files, audio files or torrent files.",
	),
	"grounding" => array(
		"title" => "Can be grounded against",
		"description" => "The endpoint can be grounded against: mo:Track objects are linked via mo:available_as statements to either mo:AudioFile objects or URLs which when resolved give MP3 files.",
	),
);

if (isset($_REQUEST["endpointurl"])) {
	$errors = array();
	$queries = array();
	$capabilitytriples = exploreendpoint($_REQUEST["endpointurl"], $errors, $queries);
	if (empty($errors)) {
		$title = "Sparql endpoint results";
		include "htmlheader.php";
		?>
		<h1><?php echo htmlspecialchars($title); ?></h1>
		<p>The endpoint <code><?php echo htmlspecialchars($_REQUEST["endpointurl"]); ?></code> could be connected to and queried.</p>
		<?php if (empty($capabilitytriples)) { ?>
			<p>Some probing found that the endpoint doesn't have any information this application can use.</p>
			<p>We expected a row of response for at least one of the following queries.</p>
			<ul>
				<?php foreach ($queries as $query) { ?>
					<li><pre><?php echo htmlspecialchars($query); ?></pre></li>
				<?php } ?>
			</ul>
			<?php
			include "htmlfooter.php";
			exit;
			?>
		<?php } ?>
		<p>Some probing of the endpoint found the following capabilities.</p>
		<dl>
			<?php foreach ($capabilitytriples as $cap => $triples) { ?>
				<dt><?php echo htmlspecialchars($capabilities[$cap]["title"]); ?></dt>
				<dd>
					<p><?php echo htmlspecialchars($capabilities[$cap]["description"]); ?></p>
					<?php if (!empty($triples)) { ?>
						<p>This was determined by finding the following example triples.</p>
						<?php
						$graph = new Graphite($GLOBALS["ns"]);
						$graph->addTriples($triples);
						echo $graph->dump();
						?>
					<?php } ?>
				</dd>
			<?php } ?>
		</dl>
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
