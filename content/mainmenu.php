<?php include "htmlheader.php"; ?>
<h2>Main menu</h2>
<dl>
	<dt><a href="<?php echo SITEROOT_WEB; ?>newcollection">New collection</a></dt>
	<dd>Build a new collection of signals by filtering <a href="http://dbtune.org/">DBTune</a>'s linked data representation of music from <a href="http://www.jamendo.com/">Jamendo</a>.</dd>

	<dt><a href="<?php echo SITEROOT_WEB; ?>existingcollections">Existing collections</a></dt>
	<dd>View a table of existing collections with links to see their results and ground them against audio file repositories.</dd>

	<dt><a href="<?php echo SITEROOT_WEB; ?>viewcollectionresults">View results from a single collection</a></dt>
	<dd>Enter a signal collection's URI to view any analysis results stored for its signals.</dd>

	<dt><a href="<?php echo SITEROOT_WEB; ?>viewmanycollectionresults">Compare results from multiple collections</a></dt>
	<dd>Enter multiple signal collections' URIs to compare their analyses' results.</dd>
</dl>
<?php include "htmlfooter.php"; ?>
