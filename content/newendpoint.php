<?php

require_once SITEROOT_LOCAL . "include/arc/ARC2.php";
require_once SITEROOT_LOCAL . "include/Graphite.php";

if (isset($_REQUEST["endpointurl"])) {
	if (Endpoint::exists($_REQUEST["endpointurl"])) {
		flash("An endpoint with that URL is already saved -- taken you to its edit page");
		redirect("editendpoint?id=" . md5($_REQUEST["endpointurl"]));
	}
	$endpoint = new Endpoint($_REQUEST["endpointurl"], $_REQUEST["endpointname"], @$_REQUEST["endpointdescription"]);

	// if it's possible we could save this, store it in session data for now
	if (count($endpoint->errors()) == 0 && count($endpoint->capabilities()) > 0)
		$_SESSION["pendingendpoint"] = $endpoint;
	else if (isset($_SESSION["pendingendpoint"]))
		unset($_SESSION["endingendpoint"]);

	if (count($endpoint->errors()) == 0) {
		$title = "Sparql endpoint results";
		include "htmlheader.php";
		?>
		<h2><?php echo htmlspecialchars($title); ?></h2>
		<p>The endpoint <strong><?php echo htmlspecialchars($_REQUEST["endpointname"]); ?></strong> (<code><?php echo htmlspecialchars($_REQUEST["endpointurl"]); ?></code>) could be connected to and queried.</p>
		<?php if (count($endpoint->capabilities()) == 0) { ?>
			<p>Some probing found that the endpoint doesn't have any information this application can use.</p>
			<p>We expected a row of response for at least one of the following queries.</p>
			<ul>
				<?php foreach ($endpoint->probequeries() as $query) { ?>
					<li><pre><?php echo htmlspecialchars($query); ?></pre></li>
				<?php } ?>
			</ul>

			<h3>Actions</h3>
			<ul>
				<li><a href="<?php echo SITEROOT_WEB; ?>newendpoint">Try another endpoint</a></li>
			</ul>
			<?php
			include "htmlfooter.php";
			exit;
			?>
		<?php } ?>
		<p>Some probing of the endpoint found the following capabilities.</p>
		<dl>
			<?php foreach ($endpoint->capabilities() as $cap) { ?>
				<dt><?php echo htmlspecialchars($cap->name()); ?></dt>
				<dd>
					<p><?php echo htmlspecialchars($cap->description()); ?></p>
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
		<h3>Actions</h3>
		<ul>
			<li><a href="<?php echo SITEROOT_WEB; ?>saveendpoint">Save this endpoint</a></li>
		</ul>
		<?php
		include "htmlfooter.php";
		exit;
	}
}

$title = "New Sparql endpoint";
include "htmlheader.php";
?>

<h2><?php echo htmlspecialchars($title); ?></h2>

<?php if (isset($endpoint) && count($endpoint->errors()) > 0) { ?>
	<div class="errors">
		<h3>Errors</h3>
		<ul>
			<?php foreach ($endpoint->errors() as $error) { ?>
				<li><?php echo htmlspecialchars($error); ?></li>
			<?php } ?>
		</ul>
	</div>
<?php } ?>

<p>We expect to find objects from the <a href="http://musicontology.com/">Music Ontology</a> such as mo:Signal, mo:Track, mo:Record, mo:MusicArtist and, for grounding, mo:AudioFile.</p>
<p>TODO: graph of the kind of stuff we expect</p>

<form action="<?php echo SITEROOT_WEB; ?>newendpoint" method="get">
	<dl>
		<dt><label for="endpointurl">Endpoint URL</label></dt>
		<dd>
			<input type="text" name="endpointurl" id="endpointurl" size="64"<?php if (isset($_REQUEST["endpointurl"])) { ?> value="<?php echo htmlspecialchars($_REQUEST["endpointurl"]); ?>"<?php } ?>>
			<span class="hint">Some endpoints are sensitive about whether or not they have a trailing slash</span>
		</dd>

		<dt><label for="endpointname">Endpoint name</label></dt>
		<dd>
			<input type="text" name="endpointname" id="endpointname" size="64"<?php if (isset($_REQUEST["endpointname"])) { ?> value="<?php echo htmlspecialchars($_REQUEST["endpointname"]); ?>"<?php } ?>>
		</dd>

		<dt><label for="endpointdescription">Description</label></dt>
		<dd>
			<textarea type="text" name="endpointdescription" id="endpointdescription" cols="64" rows="4"><?php if (isset($_REQUEST["endpointdescription"])) echo htmlspecialchars($_REQUEST["endpointdescription"]); ?></textarea>
			<span class="hint">Optional</span>
		</dd>

		<dt>Test endpoint</dt>
		<dd>
			<input type="submit" name="testendpoint" value="Test endpoint">
		</dd>
	</dl>
</form>
<?php include "htmlfooter.php"; ?>
