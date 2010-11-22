<?php

$collections = getcollections();

$title = "Table of existing collections";
include "htmlheader.php";
?>

<h2><?php echo htmlspecialchars($title); ?></h2>

<div class="trythis collapsed">
	<div class="content">
		<p>This is a table of existing collections. If you've already made a new collection you can filter it to see just your own collections.</p>
		<p>The table is sortable – click the column headings.</p>
		<p>You can click "view" to see any results which are available for that collection's signals. Not all collections will have results yet, but if the collection has been grounded (that is, its signal descriptions linked to actual audiofiles) you can run the collection through the analysis flow in MyExperiment and results will begin to appear.</p>
		<p>Alternatively you can check boxes on two or more collections and then click "compare selected collections" at the bottom to see the results side by side.</p>
		<ul>
			<li>Try comparing "German dance" to "Finnish metal".</li>
		</ul>
	</div>
</div>

<?php if (isset($_REQUEST["author"])) { ?>
	<?php if (isset($_COOKIE["cc_author"]) && $_COOKIE["cc_author"] == $_REQUEST["author"]) { ?>
		<p class="hint">Currently viewing collections by <?php echo htmlspecialchars($_REQUEST["author"]); ?>, which was found as a cookie. <a href="<?php echo SITEROOT_WEB; ?>clearcookie">Click here to clear the cookie</a> (for instance if you're not <?php echo htmlspecialchars($_REQUEST["author"]); ?>).</p>
	<?php } ?>
	<ul>
		<li><a href="<?php echo SITEROOT_WEB; ?>existingcollections">View all collections</a></li>
	</ul>
<?php } else if (isset($_COOKIE["cc_author"])) { ?>
	<ul>
		<li><a href="<?php echo SITEROOT_WEB; ?>existingcollections?author=<?php echo htmlspecialchars($_COOKIE["cc_author"]); ?>">
			View only collections by <?php echo htmlspecialchars($_COOKIE["cc_author"]); ?>
		</a></li>
	</ul>
<?php } ?>

<h3>
	All existing collections
	<?php if (isset($_REQUEST["author"])) { ?>
		by <?php echo $_REQUEST["author"]; ?>
	<?php } ?>
</h3>
<form action="<?php echo SITEROOT_WEB; ?>viewcollectionresults" method="get">
	<table class="tablesorter {sortlist: [[2,1]]}">
		<thead>
			<tr>
				<th>Title</th>
				<th>Author</th>
				<th>Published</th>
				<th>Description</th>
				<th>Size</th>
				<th class="{sorter: false}">RDF</th>
				<th class="{sorter: false}">Groundings</th>
				<th class="{sorter: false}">Results</th>
				<th class="{sorter: false}">Compare results</th>
				<th class="{sorter: false}">Actions</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($collections as $collection) {
				$aggregate = $collection["index"][$collection["uri"] . "#aggregate"];
				if (!isset($_REQUEST["author"]) || isset($_REQUEST["author"]) && $aggregate[$ns["dc"] . "creator"][0] == $_REQUEST["author"]) {
					?>
					<tr id="collection_<?php echo $collection["hash"]; ?>">
						<td><?php echo htmlspecialchars($aggregate[$ns["dc"] . "title"][0]); ?></td>
						<td><?php echo htmlspecialchars($aggregate[$ns["dc"] . "creator"][0]); ?></td>
						<td><?php echo date("Y-m-d H:i:s O", $collection["modified"]); ?></td>
						<td><?php if (isset($aggregate[$ns["dc"] . "description"])) echo htmlspecialchars($aggregate[$ns["dc"] . "description"][0]); ?></td>
						<td><?php echo count($aggregate[$ns["ore"] . "aggregates"]); ?></td>
						<td><?php echo urilink($collection["uri"], "Collection URI and RDF representation"); ?></td>
						<td><?php if (empty($collection["groundings"])) { ?>
							None
						<?php } else { ?>
							<ul>
								<?php foreach ($collection["groundings"] as $grounding) { ?>
									<li>
										<?php echo urilink($grounding["uri"], "Grounded collection URI and RDF representation"); ?>
										(<?php echo count($grounding["index"][$grounding["uri"] . "#aggregate"][$ns["ore"] . "aggregates"]); ?> files)
										&ndash;
										<a href="http://myexperiment.nema.ecs.soton.ac.uk/workflows/all?collection=<?php echo urlencode($grounding["uri"]); ?>">Run in MyExperiment</a>
									</li>
								<?php } ?>
							</ul>
						<?php } ?></td>
						<td><a href="<?php echo SITEROOT_WEB; ?>viewcollectionresults?uri[]=<?php echo urlencode($collection["uri"]); ?>">View</a></td>
						<td><input type="checkbox" name="uri[]" value="<?php echo htmlspecialchars($collection["uri"]); ?>"></td>
						<td><?php if ($aggregate[$ns["dc"] . "creator"][0] != "bjn") { ?>
							<ul>
								<li><input type="button" class="deletebutton" value="Delete" id="deletebutton_<?php echo $collection["hash"]; ?>"></li>
							</ul>
						<?php } ?></td>
					</tr>
				<?php }
			} ?>
		</tbody>
	</table>

	<input type="submit" name="compare" value="Compare selected collections">
</form>
<?php
include "htmlfooter.php";
?>
