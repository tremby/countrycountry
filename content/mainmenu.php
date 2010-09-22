<?php
$title = "Main menu";
include "htmlheader.php";
?>
<h2><?php echo htmlspecialchars($title); ?></h2>
<dl>
	<dt><a href="<?php echo SITEROOT_WEB; ?>newcollection">New collection</a></dt>
	<dd>Build a new collection of signals by filtering <a href="http://dbtune.org/">DBTune</a>'s linked data representation of music from <a href="http://www.jamendo.com/">Jamendo</a>.</dd>

	<dt><a href="<?php echo SITEROOT_WEB; ?>existingcollections">Existing collections</a></dt>
	<dd>View a table of existing collections with links to see their results and ground them against audio file repositories.</dd>

	<dt><a href="<?php echo SITEROOT_WEB; ?>viewcollectionresults">View collection results</a></dt>
	<dd>Enter a single collection's URI or multiple collections' URIs to see or compare the results.</dd>
</dl>
<?php include "htmlfooter.php"; ?>
