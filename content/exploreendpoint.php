<?php

require_once SITEROOT_LOCAL . "include/arc/ARC2.php";
require_once SITEROOT_LOCAL . "include/Graphite.php";

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
	$endpoint = new Endpoint($_REQUEST["endpointurl"]);

	if (count($endpoint->errors()) == 0) {
		$title = "Sparql endpoint results";
		include "htmlheader.php";
		?>
		<h1><?php echo htmlspecialchars($title); ?></h1>
		<p>The endpoint <code><?php echo htmlspecialchars($_REQUEST["endpointurl"]); ?></code> could be connected to and queried.</p>
		<?php if (count($endpoint->capabilities()) == 0) { ?>
			<p>Some probing found that the endpoint doesn't have any information this application can use.</p>
			<p>We expected a row of response for at least one of the following queries.</p>
			<ul>
				<?php foreach ($endpoint->probequeries() as $query) { ?>
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
			<?php foreach ($endpoint->capabilitytriples() as $cap => $triples) { ?>
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

<?php if (isset($endpoint) && count($endpoint->errors()) > 0) { ?>
	<div class="errors">
		<h2>Errors</h2>
		<ul>
			<?php foreach ($endpoint->errors() as $error) { ?>
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
