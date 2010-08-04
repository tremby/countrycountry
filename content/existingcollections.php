<?php

$uristem = "http://collections.nema.ecs.soton.ac.uk/";

require_once "include/arc/ARC2.php";
$collections = array();
foreach (glob(SITEROOT_LOCAL . "signalcollections/*.xml") as $filename) {
	$parser = ARC2::getRDFParser();
	$parser->parse($filename);
	$collection = array();
	$collection["index"] = $parser->getSimpleIndex();
	$collection["modified"] = filemtime($filename);
	$collection["hash"] = preg_replace('%.*/([0-9a-f]+)\.xml$%', '\1', $filename);
	$collection["uri"] = $uristem . "signalcollection/" . $collection["hash"];

	// get groundings
	$collection["groundings"] = array();
	foreach (glob(SITEROOT_LOCAL . "filecollections/" . $collection["hash"] . "/*.xml") as $gfile) {
		$parser = ARC2::getRDFParser();
		$parser->parse($gfile);
		$grounding = array();
		$grounding["index"] = $parser->getSimpleIndex();
		$grounding["modified"] = filemtime($gfile);
		$grounding["hash"] = preg_replace('%.*/([0-9a-f]+)\.xml$%', '\1', $gfile);
		$grounding["uri"] = $uristem . "filecollection/" . $collection["hash"] . "/" . $grounding["hash"];
		$collection["groundings"][] = $grounding;
	}

	$collections[] = $collection;
}

include "htmlheader.php";
//echo "<pre>" . htmlspecialchars(print_r($collections, true)) . "</pre>";
?>
<table>
	<tr>
		<th>Title</th>
		<th>Author</th>
		<th>Published</th>
		<th>Description</th>
		<th>Size</th>
		<th>RDF</th>
		<th>Groundings</th>
		<th>Results</th>
	</tr>
	<?php foreach ($collections as $collection) { $aggregate = $collection["index"][$collection["uri"] . "#aggregate"]; ?>
		<tr>
			<td><?php echo htmlspecialchars($aggregate[$ns["dc"] . "title"][0]); ?></td>
			<td><?php echo htmlspecialchars($aggregate[$ns["dc"] . "creator"][0]); ?></td>
			<td><?php echo date("Y-m-d H:i:s O", $collection["modified"]); ?></td>
			<td><?php if (isset($aggregate[$ns["dc"] . "description"])) echo htmlspecialchars($aggregate[$ns["dc"] . "description"][0]); ?></td>
			<td><?php echo count($aggregate[$ns["ore"] . "aggregates"]); ?></td>
			<td><?php echo urilink($collection["uri"]); ?></td>
			<td><?php if (empty($collection["groundings"])) { ?>
				None
			<?php } else { ?>
				<ul>
					<?php foreach ($collection["groundings"] as $grounding) { ?>
						<li><?php echo urilink($grounding["uri"], count($grounding["index"][$grounding["uri"] . "#aggregate"][$ns["ore"] . "aggregates"]) . " files"); ?></li>
					<?php } ?>
				</ul>
			<?php } ?></td>
			<td><a href="<?php echo SITEROOT_WEB; ?>viewcollectionresults?uri=<?php echo urlencode($collection["uri"]); ?>">View</a></td>
		</tr>
	<?php } ?>
</table>
<?php
include "htmlfooter.php";
?>
