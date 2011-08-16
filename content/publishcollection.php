<?php

if (!isset($_REQUEST["id"]))
	badrequest("No collection ID specified");
$collection = Collection::fromID($_REQUEST["id"]);
if (!$collection)
	badrequest("No collection found in session data for specified ID");

$filename = SITEROOT_LOCAL . "/signalcollections/" . $collection->id() . ".xml";
if (file_exists($filename))
	badrequest("A signal collection with this ID has already been published at\nhttp://collections.nema.linkedmusic.org/signalcollection/" . $collection->id());
file_put_contents($filename, $collection->rdf());

$title = "Collection published";
include "htmlheader.php";

?>

<h2><?php echo htmlspecialchars($title); ?></h2>

<div class="trythis collapsed">
	<div class="content">
		<p>You've published your collection and it has had a new URI minted for it. The collection can't be analysed until it has been <strong>grounded</strong> – that is, its descriptions of signals linked to actual audio files by comparing against an audiofile respository.</p>
		<p>You can ground the collection by clicking the link below.</p>
	</div>
</div>

<p>The signal collection "<?php echo htmlspecialchars($collection->title()); ?>" has been published with the following URI.
<br><code><?php echo htmlspecialchars($collection->uri()); ?></code></p>

<h3>Actions</h3>
<ul>
	<li><a href="<?php echo SITEROOT_WEB; ?>groundcollection?id=<?php echo $_REQUEST["id"]; ?>">Ground this collection</a></li>
	<li><a href="<?php echo SITEROOT_WEB; ?>existingcollections">View all existing collections</a></li>
	<li><a href="<?php echo SITEROOT_WEB; ?>">Back to the main menu</a></li>
</ul>

<?php
include "htmlfooter.php";
?>
