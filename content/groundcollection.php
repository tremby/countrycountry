<?php

$errors = array();
$endpoints = array();

if (!isset($_REQUEST["id"]))
	badrequest("No collection ID specified");
$collection = Collection::fromID($_REQUEST["id"]);
if (!$collection)
	badrequest("No collection found in session data for specified ID");

if (isset($_POST["groundcollection"])) {
	if (!isset($_POST["endpoints"]) || empty($_POST["endpoints"]))
		$errors[] = "You need to select at least one endpoint";
	else {
		$endpoints = Endpoint::load($_POST["endpoints"], true);
		foreach ($endpoints as $ep)
			if (!$ep->hascapability("grounding"))
				$errors[] = "Endpoint '" . $ep->name() . "' doesn't have links to audiofiles and so can't be used for grounding a collection";
	}

	if (empty($errors)) {
		$dir = SITEROOT_LOCAL . "/filecollections/" . $collection->id();
		if (!file_exists($dir))
			mkdir($dir);

		$collection->ground($endpoints);

		if (count($collection->groundedresults()) > 0)
			$title = "Collection grounded";
		else
			$title = "No audiofiles found";
		include "htmlheader.php";

		if (count($collection->groundedresults()) > 0) {
			file_put_contents($dir . "/" . $collection->groundhash() . ".xml", $collection->groundedrdf());
			?>
			<h2><?php echo htmlspecialchars($title); ?></h2>

			<div class="trythis collapsed">
				<div class="content">
					<p>You've grounded this collection. You'll see a count below of the audiofiles which were found matching the signals and you have the option to view any results which may already exist or to run a new analysis in MyExperiment.</p>
				</div>
			</div>

			<p>The signal collection "<?php echo htmlspecialchars($collection->title()); ?>" has been grounded using the chosen endpoints and published with the following URI.
			<br>
			<code><?php echo htmlspecialchars($collection->groundeduri()); ?></code></p>
			<p>Of the signal collection's <?php echo count($collection->results()); ?> tracks, audiofiles for <?php echo count($collection->groundedresults()); ?> were found.</p>
		<?php } else { ?>
			<h2><?php echo htmlspecialchars($title); ?></h2>
			<p>Audiofiles for none of the <?php echo count($collection->results()); ?> signals in the collection "<?php echo htmlspecialchars($collection->title()); ?>" were found when querying the signal repository endpoint.</p>
		<?php } ?>

		<h3>Actions</h3>
		<ul>
			<?php if (count($collection->groundedresults()) > 0) { ?>
				<li><a href="http://myexperiment.nema.ecs.soton.ac.uk/workflows/all?collection=<?php echo urlencode($collection->groundeduri()); ?>">Run in MyExperiment</a></li>
				<li><a href="<?php echo SITEROOT_WEB; ?>viewcollectionresults?uri[]=<?php echo urlencode($collection->uri()); ?>">View collection results</a> (there may not be any yet!)</li>
			<?php } ?>
			<li><a href="<?php echo SITEROOT_WEB; ?>existingcollections">View all existing collections</a></li>
			<li><a href="<?php echo SITEROOT_WEB; ?>">Back to the main menu</a></li>
		</ul>

		<?php
		include "htmlfooter.php";
		exit;
	}
}

$title = "Ground collection";
include "htmlheader.php";
?>

<h2><?php echo htmlspecialchars($title); ?></h2>

<?php if (!empty($errors)) { ?>
	<div class="errors">
		<h3>Errors</h3>
		<ul>
			<?php foreach ($errors as $error) { ?>
				<li><?php echo htmlspecialchars($error); ?></li>
			<?php } ?>
		</ul>
	</div>
<?php } ?>

<p>First you need to choose which endpoints you want to ground your collection against.</p>

<?php if (count(Endpoint::allwith("grounding")) == 0) { ?>
	<p>No endpoints with links to audiofiles are currently defined.</p>
<?php } else { ?>
	<form action="<?php echo SITEROOT_WEB; ?>groundcollection" method="post">
		<ul>
			<?php foreach (Endpoint::allwith("grounding") as $endpoint) { ?>
				<li>
					<label>
						<input type="checkbox" name="endpoints[]" id="endpoints_<?php echo $endpoint->hash(); ?>" value="<?php echo $endpoint->hash(); ?>"<?php if (!isset($_POST["newcollection"]) || isset($_POST["endpoints"]) && in_array($endpoint->hash(), $_POST["endpoints"])) { ?> checked="checked"<?php } ?>>
						<?php echo htmlspecialchars($endpoint->name()); ?>
					</label>
					<p class="hint"><?php echo htmlspecialchars($endpoint->description()); ?></p>
				</li>
			<?php } ?>
		</ul>
		<input type="hidden" name="id" value="<?php echo $collection->id(); ?>">
		<input type="submit" name="groundcollection" value="Ground collection">
	</form>
<?php } ?>

<?php
include "htmlfooter.php";
?>
