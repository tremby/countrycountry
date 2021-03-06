<?php

if (!isset($_REQUEST["id"]))
	badrequest("No collection ID specified");
$collection = Collection::fromID($_REQUEST["id"]);
if (!$collection)
	badrequest("No collection found in session data for specified ID");

$dir = SITEROOT_LOCAL . "/filecollections/" . $collection->id();
if (!file_exists($dir))
	mkdir($dir);

$collection->ground(ENDPOINT_REPOSITORY);

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

	<p>The signal collection "<?php echo htmlspecialchars($collection->title()); ?>" has been grounded to the audiofile repository and published with the following URI.
	<br><code><?php echo htmlspecialchars($collection->groundeduri()); ?></code></p>
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
