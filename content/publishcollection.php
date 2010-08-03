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

include "htmlheader.php";

?>

<h2>Collection published</h2>
<p>The signal collection "<?php echo htmlspecialchars($collection->title()); ?>" has been published with the following URI.
<br><code><?php echo htmlspecialchars($collection->uri()); ?></code></p>

<h3>Actions</h3>
<ul>
	<li><a href="<?php echo SITEROOT_WEB; ?>groundcollection/<?php echo $collection->id(); ?>">Ground this collection</a></li>
</ul>

<?php
include "htmlfooter.php";
?>
