<?php
$title = "Meandre is broken!";
include "htmlheader.php";
?>
<h2><?php echo htmlspecialchars($title); ?></h2>

<p>At the moment Meandre, which Country/country was using, is broken. At this 
point the user would log in to Myexperiment to choose a Meandre workflow to run 
over the collection and then start it.</p>
<p>The classifications which had already been run still exist and so with any 
luck some will match the signals in your collection.</p>
<ul>
	<li><a href="<?php echo SITEROOT_WEB; ?>viewcollectionresults?uri[]=<?php echo urlencode($_GET["collection"]); ?>">View any results we might already have for this collection</a></li>
</ul>

<?php include "htmlfooter.php"; ?>
