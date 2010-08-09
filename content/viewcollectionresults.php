<?php

if (!isset($_REQUEST["uri"])) {
	include "htmlheader.php";
	?>
	<h2>View collection results</h2>
	<form action="<?php echo SITEROOT_WEB; ?>viewcollectionresults" method="get">
		<dl>
			<dt><label for="uri">Collection URI</label></dt>
			<dd>
				<input type="text" size="64" name="uri" id="uri">
				(ungrounded)
			</dd>

			<dt>View</dt>
			<dd><input type="submit" name="submit" value="Submit"></dd>
		</dl>
	</form>
	<?php
	include "htmlfooter.php";
	exit;
}

require_once SITEROOT_LOCAL . "include/arc/ARC2.php";

// get collection triples
$collectionuri = $_REQUEST["uri"];
$parser = ARC2::getRDFParser();
$parser->parse($_REQUEST["uri"]);
if (!empty($parser->errors))
	servererror("errors parsing collection RDF: " . print_r($parser->errors, true));
$index = $parser->getSimpleIndex();

// get signals in the collection
$aggregateuri = $index[$collectionuri][$ns["ore"] . "describes"][0];
$signals = $index[$aggregateuri][$ns["ore"] . "aggregates"];
sort($signals);

// set up results endpoint
$config = array("remote_store_endpoint" => ENDPOINT_RESULTS);
$store = ARC2::getRemoteStore($config);

$assertions = array();
$classifier_genre = array();
$classifier_maxweight = array();

// for each signal
foreach ($signals as $signal) {
	// get associations for which this track is the subject
	$query = prefix(array("mo", "sim", "pv")) . "
		SELECT * WHERE {
			?genreassociation
				sim:subject <$signal> ;
				sim:object ?musicgenre ;
				sim:weight ?weight ;
				sim:method ?associationmethod .
			?associationmethod
				pv:usedGuideline ?classifier .
		}
		ORDER BY ?classifier ?musicgenre
	";
	//echo $query;

	$rows = $store->query($query, "rows");

	// collect assertions about this track's genre
	$assertions[$signal] = array();
	foreach ($rows as $row) {
		// if we haven't seen this classifier, note it
		if (!in_array($row["classifier"], array_keys($classifier_genre)))
			$classifier_genre[$row["classifier"]] = array();
		// if we haven't seen this genre in this classifier, note it
		if (!in_array($row["musicgenre"], $classifier_genre[$row["classifier"]]))
			$classifier_genre[$row["classifier"]][] = $row["musicgenre"];

		//print_r($row);
		if (!isset($assertions[$signal][$row["classifier"]]))
			$assertions[$signal][$row["classifier"]] = array();

		// skip if we already have a result for this track, classifier and genre
		if (isset($assertions[$signal][$row["classifier"]][$row["musicgenre"]]))
			continue;

		// store this result
		$assertions[$signal][$row["classifier"]][$row["musicgenre"]] = floatval($row["weight"]);

		// update maximum weight for this classifier if appropriate
		if (!isset($classifier_maxweight[$row["classifier"]]) || $classifier_maxweight[$row["classifier"]] < floatval($row["weight"]))
			$classifier_maxweight[$row["classifier"]] = floatval($row["weight"]);
	}
}

include "htmlheader.php";
?>

<h2>Results for collection "<?php echo htmlspecialchars($index[$aggregateuri][$ns["dc"] . "title"][0]); ?>"</h2>

<div class="cols">
	<div class="cell">
		<h3>Collection information and basic statistics</h3>
		<dl>
			<dt>Collection</dt>
			<dd>
				<strong><?php echo htmlspecialchars($index[$aggregateuri][$ns["dc"] . "title"][0]); ?></strong>,
				created by <?php $uri = $index[$aggregateuri][$ns["dc"] . "creator"][0]; echo strpos("http://", $uri) === 0 ? urilink($uri) : htmlspecialchars($uri); ?>
				<?php if (isset($index[$aggregateuri][$ns["dc"]  . "description"])) { ?>
					<br>
					<small>
						<?php echo htmlspecialchars($index[$aggregateuri][$ns["dc"]  . "description"][0]); ?>
					</small>
				<?php } ?>
			</dd>

			<dt>Number of signals in collection</dt>
			<dd><?php echo count($signals); ?></dd>

			<dt>Number of classifiers which have been run on any of these signals</dt>
			<dd><?php echo count($classifier_genre); ?></dd>

			<dt>Number of signals for which results are available, for each classifier</dt>
			<dd>
				<dl>
					<?php foreach ($classifier_genre as $classifier => $genres) { ?>
						<dt><?php echo htmlspecialchars(classifiermapping($classifier)); ?></dt>
						<dd><?php $count = 0; foreach ($signals as $signal) if (isset($assertions[$signal][$classifier])) $count++; echo $count; ?></dd>
					<?php } ?>
				</dl>
			</dd>
		</dl>
	</div>

	<div class="cell">
		<?php if (empty($classifier_genre)) { ?>
			<p>No data is yet available</p>
			</div></div>
			<?php include "htmlfooter.php"; exit; ?>
		<?php } ?>

		<h3>Average weightings of each genre over the whole collection</h3>
		<?php
		// for each classifier, get the average weight of each classification
		$classifier_averageweight = array();
		foreach ($classifier_genre as $classifier => $genres) {
			$classifier_averageweight[$classifier] = array();
			foreach ($genres as $genre) {
				$weights = array();
				foreach ($signals as $signal)
					if (isset($assertions[$signal][$classifier][$genre]))
						$weights[] = $assertions[$signal][$classifier][$genre];
				if (count($weights) == 0)
					$classifier_averageweight[$classifier][$genre] = 0;
				else
					$classifier_averageweight[$classifier][$genre] = array_sum($weights) / count($weights);
			}
		}
		?>
		<dl class="single">
			<?php foreach ($classifier_genre as $classifier => $genres) { ?>
				<dt><?php echo htmlspecialchars(classifiermapping($classifier)); ?></dt>
				<dd>
					<div id="averagechart_<?php echo md5($classifier); ?>" style="width: 500px; height: 300px;"></div>
					<?php
					$data = array();
					foreach ($genres as $genre) {
						$data[] = array("label" => uriendpart($genre), "data" => $classifier_averageweight[$classifier][$genre]);
					}
					?>
					<script type="text/javascript">
						$.plot($("#averagechart_<?php echo md5($classifier); ?>"), <?php echo json_encode($data); ?>, {series:{pie:{show:true}}});
					</script>
				</dd>
			<?php } ?>
		</dl>
	</div>
</div>

<h3>External links for the heaviest weighted genre</h3>
<dl class="single">
	<?php foreach ($classifier_averageweight as $classifier => $genre_weight) {
		arsort($genre_weight, SORT_NUMERIC);
		$heavy = array_shift(array_keys($genre_weight));
		?>
		<dt><?php echo htmlspecialchars(classifiermapping($classifier)); ?></dt>
		<dd>
			<p>Most heavily weighted genre: <strong><?php echo htmlspecialchars(uriendpart($heavy)); ?></strong> <?php echo urilink($heavy); ?></p>
			<h4>Some random artists from the same genre:</h4>
			<?php
			$artists = dbpediaartists($heavy);
			if (empty($artists)) { ?>
				<p>No artists matching this genre were found in DBpedia.</p>
			<?php } else { ?>
				<ul>
					<?php foreach (array_rand($artists, min(count($artists), 10)) as $k) { ?>
						<li>
							<a href="<?php echo htmlspecialchars($artists[$k]["artist"]); ?>">
								<?php echo htmlspecialchars($artists[$k]["artistname"]); ?>
							</a>
							from
							<a href="<?php echo htmlspecialchars($artists[$k]["place"]); ?>">
								<?php echo htmlspecialchars($artists[$k]["placename"]); ?>
							</a>
						</li>
					<?php } ?>
				</ul>
			<?php } ?>
		</dd>
	<?php } ?>
</dl>

<h3>Most and least</h3>
<?php
$classifier_genre_mostleast = array();
foreach ($classifier_genre as $classifier => $genres) {
	$classifier_genre_mostleast[$classifier] = array();
	foreach ($genres as $genre) {
		$classifier_genre_mostleast[$classifier][$genre] = array(null, null);
		$least = null;
		$most = null;
		foreach ($signals as $signal) {
			if (isset($assertions[$signal][$classifier][$genre])) {
				if (is_null($least) || $assertions[$signal][$classifier][$genre] < $least) {
					$least = $assertions[$signal][$classifier][$genre];
					$classifier_genre_mostleast[$classifier][$genre][0] = $signal;
				}
				if (is_null($most) || $assertions[$signal][$classifier][$genre] > $most) {
					$most = $assertions[$signal][$classifier][$genre];
					$classifier_genre_mostleast[$classifier][$genre][1] = $signal;
				}
			}
		}
	}
}
?>
<dl class="single">
	<?php foreach ($classifier_genre_mostleast as $classifier => $genres) { ?>
		<dt><?php echo htmlspecialchars(classifiermapping($classifier)); ?></dt>
		<dd>
			<dl class="single">
				<?php foreach ($genres as $genre => $leastmost) { ?>
					<dt><?php echo htmlspecialchars(uriendpart($genre)); ?></dt>
					<dd>
						<dl>
							<dt>Least</dt>
							<dd>
								<?php $info = signalinfo($leastmost[0]); ?>
								<a href="<?php echo SITEROOT_WEB; ?>viewsignalresults?uri=<?php echo urlencode($leastmost[0]); ?>">
									<em><?php echo htmlspecialchars($info["trackname"]); ?></em>
									by <?php echo htmlspecialchars($info["artistname"]); ?>
								</a>
								(weight: <?php echo sprintf("%.2f", $assertions[$leastmost[0]][$classifier][$genre]); ?>)
							</dd>
							<dt>Most</dt>
							<dd>
								<a href="<?php echo SITEROOT_WEB; ?>viewsignalresults?uri=<?php echo urlencode($leastmost[1]); ?>">
									<?php $info = signalinfo($leastmost[1]); ?>
									<em><?php echo htmlspecialchars($info["trackname"]); ?></em>
								by <?php echo htmlspecialchars($info["artistname"]); ?>
								</a>
								(weight: <?php echo sprintf("%.2f", $assertions[$leastmost[1]][$classifier][$genre]); ?>)
							</dd>
						</dl>
					</dd>
				<?php } ?>
			</dl>
		</dd>
	<?php } ?>
</dl>

<!--
<h3>Count of signals' heaviest weighted genres</h3>
<?php
// for each classifier, count the number of signals for which each 
// classification has the heaviest weight
$classifier_heaviestgenrecount = array();
foreach ($classifier_genre as $classifier => $genres) {
	$classifier_heaviestgenrecount[$classifier] = array();
	foreach ($genres as $genre)
		$classifier_heaviestgenrecount[$classifier][$genre] = 0;
	foreach ($signals as $signal) {
		$w = -1;
		if (!isset($assertions[$signal][$classifier]))
			continue;
		foreach ($assertions[$signal][$classifier] as $genre => $weight) {
			if ($weight > $w) {
				$w = $weight;
				$g = $genre;
			}
		}
		$classifier_heaviestgenrecount[$classifier][$g]++;
	}
}
?>
<dl class="single">
	<?php foreach ($classifier_genre as $classifier => $genres) { ?>
		<dt><?php echo htmlspecialchars(classifiermapping($classifier)); ?></dt>
		<dd>
			<div id="heavychart_<?php echo md5($classifier); ?>" style="width: 768px; height: 300px;"></div>
			<?php
			$data = array();
			foreach ($genres as $genre) {
				$data[] = array("label" => uriendpart($genre), "data" => $classifier_heaviestgenrecount[$classifier][$genre]);
			}
			?>
			<script type="text/javascript">
				$.plot($("#heavychart_<?php echo md5($classifier); ?>"), <?php echo json_encode($data); ?>, {series:{pie:{show:true}}});
			</script>
		</dd>
	<?php } ?>
</dl>
-->

<h3>Data table</h3>
<table width="100%" class="datatable">
	<tr>
		<th rowspan="2">Signal</th>
		<?php foreach ($classifier_genre as $classifier => $genres) { ?>
			<th colspan="<?php echo count($genres); ?>"><?php echo htmlspecialchars(classifiermapping($classifier)); ?></th>
		<?php } ?>
	</tr>
	<tr>
		<?php foreach ($classifier_genre as $classifier => $genres) foreach ($genres as $index => $genre) { ?>
			<th width="<?php echo 90 / $barcolumncount; ?>%" title="<?php echo htmlspecialchars(uriendpart($genre)); ?>"><?php echo htmlspecialchars(substr(uriendpart($genre), 0, 3)) . "&hellip;"; ?></th>
		<?php } ?>
	</tr>
	<?php
	$barcolumncount = 0;
	foreach ($classifier_genre as $classifier => $genres)
		$barcolumncount += count($genres);
	$barcolumnwidth = 90 / $barcolumncount;
	?>
	<?php foreach ($signals as $signal) { $signalinfo = signalinfo($signal); ?>
		<tr>
			<td title="<?php echo htmlspecialchars($signalinfo["artistname"]); ?> &ndash; <?php echo htmlspecialchars($signalinfo["trackname"]); ?>">
				<a href="<?php echo SITEROOT_WEB; ?>viewsignalresults?uri=<?php echo urlencode($signal); ?>">
					<?php echo htmlspecialchars($signalinfo["trackname"]); ?>
				</a>
			</td>
			<?php if (empty($assertions[$signal])) { ?>
				<td class="nodata" colspan="<?php echo $barcolumncount; ?>">
					No data
				</td>
			<?php } else foreach ($classifier_genre as $classifier => $genres) { ?>
				<?php if (!isset($assertions[$signal][$classifier])) { ?>
					<td colspan="<?php echo count($genres); ?>">No data</td>
				<?php } else foreach ($genres as $genre) { ?>
					<?php if (isset($assertions[$signal][$classifier][$genre])) { ?>
						<td width="<?php echo 90 / $barcolumncount; ?>%" title="<?php echo $assertions[$signal][$classifier][$genre]; ?>">
							<div class="barcontainer">
								<div class="bar" style="width: <?php echo 100 * $assertions[$signal][$classifier][$genre] / $classifier_maxweight[$classifier]; ?>%;"></div>
								<div class="weight"><?php echo sprintf("%.2f", $assertions[$signal][$classifier][$genre]); ?></div>
							</div>
						</td>
					<?php } else { ?>
						<td class="nodata" width="<?php echo 90 / $barcolumncount; ?>%">
							-
						</td>
					<?php } ?>
				<?php } ?>
			<?php } ?>
		</tr>
	<?php } ?>
</table>

<?php
include "htmlfooter.php";
?>
