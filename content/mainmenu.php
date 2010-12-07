<?php
$title = "Main menu";
include "htmlheader.php";
?>
<div class="trythis">
	<div class="content">
		<p>Welcome to the country/country demo. Read about the project on the <a href="<?php echo SITEROOT_WEB; ?>about">about country/country page</a>.</p>
		<p><strong>"Try this"</strong> boxes like this one appear on some pages to explain what you're seeing and a few things you can try.</p>
		<p>You might want to have a look first at the new collection or existing collections pages.</p>
		<p>On each page you'll see the <strong>"send feedback"</strong> button hanging around in the top right corner of the page. You can click this at any time to send us your thoughts. The form appears in an overlay and submits via Ajax so it won't break your flow.</p>
		<p>You can get back to this menu at any time by clicking the main title up there in the header or the "main menu" tab above.</p>
	</div>
</div>
<h2><?php echo htmlspecialchars($title); ?></h2>
<dl>
	<dt><a href="<?php echo SITEROOT_WEB; ?>about">About country/country</a></dt>
	<dd>About the project: abstract and poster</dd>

	<dt><a href="<?php echo SITEROOT_WEB; ?>newcollection">New collection</a></dt>
	<dd>Build a new collection of signals by filtering <a href="http://dbtune.org/">DBTune</a>'s linked data representation of music from <a href="http://www.jamendo.com/">Jamendo</a>.</dd>

	<dt><a href="<?php echo SITEROOT_WEB; ?>existingcollections">Existing collections</a></dt>
	<dd>View a table of existing collections with links to see their results and ground them against audio file repositories.</dd>

	<dt><a href="<?php echo SITEROOT_WEB; ?>viewcollectionresults">View collection results</a></dt>
	<dd>Enter a single collection's URI or multiple collections' URIs to see or compare the results.</dd>
</dl>
<?php include "htmlfooter.php"; ?>
