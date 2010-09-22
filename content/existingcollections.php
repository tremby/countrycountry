<?php

$collections = getcollections();

$title = "Table of existing collections";
include "htmlheader.php";
?>

<h2><?php echo htmlspecialchars($title); ?></h2>

<?php if (isset($_REQUEST["author"])) { ?>
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
					<tr>
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
						<td><ul>
							<li><a class="deletebutton" href="<?php echo SITEROOT_WEB; ?>deletecollection?id=<?php echo urlencode($collection["hash"]); ?>">Delete</a></li>
						</ul></td>
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
