<?php

if (!isset($_REQUEST["id"]))
	badrequest("No collection ID specified");
$collection = Collection::fromID($_REQUEST["id"]);
if (!$collection)
	badrequest("No collection found in session data for specified ID");

$filename = SITEROOT_LOCAL . "/signalcollections/" . $collection->id() . ".xml";
if (file_exists($filename))
	badrequest("A signal collection with this ID has already been published at\nhttp://collections.nema.ecs.soton.ac.uk/signalcollection/" . $collection->id());
file_put_contents($filename, $collection->rdf());

$title = "Collection published";
include "htmlheader.php";

?>

<h2><?php echo htmlspecialchars($title); ?></h2>
<p>The signal collection "<?php echo htmlspecialchars($collection->title()); ?>" has been published with the following URI.
<br><code><?php echo htmlspecialchars($collection->uri()); ?></code></p>

<h3>Actions</h3>
<ul>
	<li>
		<form action="<?php echo SITEROOT_WEB; ?>groundcollection/<?php echo $collection->id(); ?>" method="post">
			<input type="submit" name="ground" value="Ground this collection">
		</form>
	</li>
	<li><a href="<?php echo SITEROOT_WEB; ?>existingcollections">View all existing collections</a></li>
	<li><a href="<?php echo SITEROOT_WEB; ?>">Back to the main menu</a></li>
</ul>

<?php
include "htmlfooter.php";
?>
