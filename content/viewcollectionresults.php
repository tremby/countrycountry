<?php

if (!isset($_REQUEST["uri"]) || !is_array($_REQUEST["uri"])) {
	$collections = getcollections();

	$title = "Choose collections to view results";
	include "htmlheader.php";
	?>

	<h2><?php echo htmlspecialchars($title); ?></h2>

	<form action="<?php echo SITEROOT_WEB; ?>viewcollectionresults" method="get">
		<dl>
			<dt>Add collection</dt>
			<dd>
				<dl>
					<dt>From the list of existing collections</dt>
					<dd>
						<a id="addcollectionuridropdownbutton" href="#"><img src="<?php echo SITEROOT_WEB; ?>images/add.png" alt="Add" title="Add this collection to the list"></a>
						<select id="addcollectionuridropdown">
							<?php foreach ($collections as $collection) {
								$aggregate = $collection["index"][$collection["uri"] . "#aggregate"];
								?>
								<option value="<?php echo htmlspecialchars($collection["uri"]); ?>">
									<?php echo htmlspecialchars($aggregate[$ns["dc"] . "title"][0]); ?>
									by <?php echo htmlspecialchars($aggregate[$ns["dc"] . "creator"][0]); ?>
									(<?php echo count($aggregate[$ns["ore"] . "aggregates"]); ?> tracks)
								</option>
							<?php } ?>
						</select>
					</dd>

					<dt>By URI</dt>
					<dd>
						<a id="addcollectionuributton" href="#"><img src="<?php echo SITEROOT_WEB; ?>images/add.png" alt="Add" title="Add this URI to the list"></a>
						<input id="addcollectionuri" type="text" size="64">
					</dd>
				</dl>
			</dd>

			<dt>Collection URIs (ungrounded)</dt>
			<dd>
				<p class="hint" id="noneyet">None yet – use the controls above to add collections to be compared</p>
				<ul id="uris">
				</ul>
			</dd>

			<dt>View results</dt>
			<dd><input id="viewcollectionresultsbutton" type="submit" name="submit" value="View or compare results"></dd>
		</dl>
	</form>
	<?php
	include "htmlfooter.php";
	exit;
}

require_once SITEROOT_LOCAL . "include/arc/ARC2.php";

// set up results endpoint
$config = array("remote_store_endpoint" => ENDPOINT_RESULTS);
$store = ARC2::getRemoteStore($config);

// get collection triples
$collections = array();
foreach ($_REQUEST["uri"] as $key => $collectionuri) {
	if (empty($collectionuri)) {
		unset($_REQUEST["uri"][$key]);
		continue;
	}

	$collection = array();

	$parser = ARC2::getRDFParser();
	$parser->parse($collectionuri);
	if (!empty($parser->errors))
		servererror("errors parsing collection RDF: " . print_r($parser->errors, true));
	$collection["index"] = $parser->getSimpleIndex();

	// get signals in the collection
	$collection["collectionuri"] = $collectionuri;
	$collection["aggregateuri"] = $collection["index"][$collectionuri][$ns["ore"] . "describes"][0];
	$collection["signals"] = $collection["index"][$collection["aggregateuri"]][$ns["ore"] . "aggregates"];
	sort($collection["signals"]);

	$collection["assertions"] = array();
	$collection["classifier_genre"] = array();
	$collection["classifier_maxweight"] = array();

	// for each association
	foreach (getassociations($collection["signals"]) as $row) {
		$signal = $row["signal"];

		// collect assertions about this track's genre
		if (!isset($collection["assertions"][$signal]))
			$collection["assertions"][$signal] = array();

		// if we haven't seen this classifier, note it
		if (!in_array($row["classifier"], array_keys($collection["classifier_genre"])))
			$collection["classifier_genre"][$row["classifier"]] = array();
		// if we haven't seen this genre in this classifier, note it
		if (!in_array($row["musicgenre"], $collection["classifier_genre"][$row["classifier"]]))
			$collection["classifier_genre"][$row["classifier"]][] = $row["musicgenre"];

		if (!isset($collection["assertions"][$signal][$row["classifier"]]))
			$collection["assertions"][$signal][$row["classifier"]] = array();

		// skip if we already have a result for this track, classifier and genre
		if (isset($collection["assertions"][$signal][$row["classifier"]][$row["musicgenre"]]))
			continue;

		// store this result
		$collection["assertions"][$signal][$row["classifier"]][$row["musicgenre"]] = floatval($row["weight"]);

		// update maximum weight for this classifier if appropriate
		if (!isset($collection["classifier_maxweight"][$row["classifier"]]) || $collection["classifier_maxweight"][$row["classifier"]] < floatval($row["weight"]))
			$collection["classifier_maxweight"][$row["classifier"]] = floatval($row["weight"]);
	}

	// count up how many collections each signal appears in
	if (!isset($signal_collectioncount))
		$signal_collectioncount = array();
	foreach ($collection["signals"] as $signal)
		if (!isset($signal_collectioncount[$signal]))
			$signal_collectioncount[$signal] = 1;
		else
			$signal_collectioncount[$signal]++;

	$collections[$collectionuri] = $collection;
}

arsort($signal_collectioncount, SORT_NUMERIC);
$collectioncount = array();
foreach ($signal_collectioncount as $signal => $count) {
	if (!isset($collectioncount[$count]))
		$collectioncount[$count] = 1;
	else
		$collectioncount[$count]++;
}
krsort($collectioncount, SORT_NUMERIC);

if (count($collections) == 1)
	$title = "Collection results";
else
	$title = "Comparing " . count($_REQUEST["uri"]) . " collections";
include "htmlheader.php";
?>

<h2><?php echo htmlspecialchars($title); ?></h2>

<?php if (count($collections) != 1) { ?>
	<?php if (count($collectioncount) == 1) { ?>
		<p>No signals are shared between these collections.</p>
	<?php } else { ?>
		<ul>
			<?php foreach ($collectioncount as $numcollections => $numsignals) if ($numcollections > 1) { ?>
				<li><?php echo $numsignals; ?> signal<?php echo $numsignals == 1 ? " is" : "s are"; ?> shared between <?php echo $numcollections; ?> collections</li>
			<?php } ?>
		</ul>
	<?php } ?>
<?php } ?>

<?php if (count($collections) > 1) { ?>
	<div class="scroll"><div class="cols">
	<div id="scrollleft"></div><div id="scrollright"></div>
<?php } ?>
<?php foreach ($collections as $collectionuri => $collection) { ?>
	<?php if (count($collections) > 1) { ?>
		<div class="cell" style="min-width: 410px;">
	<?php } ?>
	<h2><?php echo htmlspecialchars($collection["index"][$collection["aggregateuri"]][$ns["dc"] . "title"][0]); ?></h2>
	<h3>Collection information and basic statistics</h3>
	<dl>
		<dt>Collection</dt>
		<dd>
			<strong><?php echo htmlspecialchars($collection["index"][$collection["aggregateuri"]][$ns["dc"] . "title"][0]); ?></strong>,
			created by <?php $uri = $collection["index"][$collection["aggregateuri"]][$ns["dc"] . "creator"][0]; echo strpos("http://", $uri) === 0 ? urilink($uri) : htmlspecialchars($uri); ?>
			<?php if (isset($collection["index"][$collection["aggregateuri"]][$ns["dc"]  . "description"])) { ?>
				<br>
				<small>
					<?php echo htmlspecialchars($collection["index"][$collection["aggregateuri"]][$ns["dc"]  . "description"][0]); ?>
				</small>
			<?php } ?>
		</dd>

		<dt>Number of signals in collection</dt>
		<dd><?php echo count($collection["signals"]); ?></dd>

		<dt>Number of classifiers which have been run on any of these signals</dt>
		<dd><?php echo count($collection["classifier_genre"]); ?></dd>

		<?php if (count($collection["classifier_genre"]) > 0) { ?>
			<dt>Number of signals for which results are available, for each classifier</dt>
			<dd>
				<dl>
					<?php foreach ($collection["classifier_genre"] as $classifier => $genres) { ?>
						<dt><?php echo htmlspecialchars(classifiermapping($classifier)); ?></dt>
						<dd><?php $count = 0; foreach ($collection["signals"] as $signal) if (isset($collection["assertions"][$signal][$classifier])) $count++; echo $count; ?></dd>
					<?php } ?>
				</dl>
			</dd>
		<?php } ?>
	</dl>

	<?php if (empty($collection["classifier_genre"])) { ?>
		<p>No data is yet available</p>
	<?php } else { ?>
		<h3>Average weightings of each genre over the whole collection</h3>
		<?php
		// for each classifier, get the average weight of each classification
		$classifier_averageweight = array();
		foreach ($collection["classifier_genre"] as $classifier => $genres) {
			$classifier_averageweight[$classifier] = array();
			foreach ($genres as $genre) {
				$weights = array();
				foreach ($collection["signals"] as $signal)
					if (isset($collection["assertions"][$signal][$classifier][$genre]))
						$weights[] = $collection["assertions"][$signal][$classifier][$genre];
				if (count($weights) == 0)
					$classifier_averageweight[$classifier][$genre] = 0;
				else
					$classifier_averageweight[$classifier][$genre] = array_sum($weights) / count($weights);
			}
		}
		?>
		<dl class="single">
			<?php foreach ($collection["classifier_genre"] as $classifier => $genres) { ?>
				<dt><?php echo htmlspecialchars(classifiermapping($classifier)); ?></dt>
				<dd>
					<div id="averagechart_<?php echo md5($collection["collectionuri"]); ?>_<?php echo md5($classifier); ?>" style="width: <?php echo count($collections) == 1 ? 500 : 370; ?>px; height: <?php echo count($collections) == 1 ? 300 : 200; ?>px;"></div>
					<?php
					$data = array();
					foreach ($genres as $genre) {
						$data[] = array("label" => uriendpart($genre), "data" => $classifier_averageweight[$classifier][$genre]);
					}
					?>
					<script type="text/javascript">
						$.plot($("#averagechart_<?php echo md5($collection["collectionuri"]); ?>_<?php echo md5($classifier); ?>"), <?php echo json_encode($data); ?>, {series:{pie:{show:true}}});
					</script>
				</dd>
			<?php } ?>
		</dl>

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
					<p class="hint">Data from DBpedia</p>
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
									<?php if (($bbcuri = bbcuri($artists[$k]["artist"])) !== false) { ?>
										<p class="hint">Further data from the BBC</p>
										<?php if (isset($bbcinfo["image"])) { ?>
											<img class="bbcimage" src="<?php echo htmlspecialchars($bbcinfo["image"]); ?>">
										<?php } ?>
										<ul>
											<?php $bbcinfo = bbcinfo($bbcuri); ?>
											<li><a href="<?php echo htmlspecialchars($bbcuri); ?>">BBC Music page</a> <?php echo urilink($bbcuri, "URI of this artist at BBC Music"); ?></li>
											<?php if (isset($bbcinfo["comment"])) { ?>
												<li><em><?php echo htmlspecialchars($bbcinfo["comment"]); ?></em></li>
											<?php } ?>
											<?php if (isset($bbcinfo["homepage"])) { ?>
												<li><a href="<?php echo htmlspecialchars($bbcinfo["homepage"]); ?>">Homepage</a></li>
											<?php }
											if (isset($bbcinfo["wikipedia"])) { ?>
												<li><a href="<?php echo htmlspecialchars($bbcinfo["wikipedia"]); ?>">Wikipedia article</a></li>
											<?php }
											if (isset($bbcinfo["musicbrainz"])) { ?>
												<li><a href="<?php echo htmlspecialchars($bbcinfo["musicbrainz"]); ?>">Musicbrainz entry</a></li>
											<?php }
											if (isset($bbcinfo["myspace"])) { ?>
												<li><a href="<?php echo htmlspecialchars($bbcinfo["myspace"]); ?>">Myspace page</a></li>
											<?php }
											if (isset($bbcinfo["imdb"])) { ?>
												<li><a href="<?php echo htmlspecialchars($bbcinfo["imdb"]); ?>">IMDB entry</a></li>
											<?php } ?>
										</ul>
									<?php } ?>
								</li>
								<div class="clearer"></div>
							<?php } ?>
						</ul>
					<?php } ?>
				</dd>
			<?php } ?>
		</dl>

		<h3>Most and least</h3>
		<?php
		$classifier_genre_mostleast = array();
		foreach ($collection["classifier_genre"] as $classifier => $genres) {
			$classifier_genre_mostleast[$classifier] = array();
			foreach ($genres as $genre) {
				$classifier_genre_mostleast[$classifier][$genre] = array(null, null);
				$least = null;
				$most = null;
				foreach ($collection["signals"] as $signal) {
					if (isset($collection["assertions"][$signal][$classifier][$genre])) {
						if (is_null($least) || $collection["assertions"][$signal][$classifier][$genre] < $least) {
							$least = $collection["assertions"][$signal][$classifier][$genre];
							$classifier_genre_mostleast[$classifier][$genre][0] = $signal;
						}
						if (is_null($most) || $collection["assertions"][$signal][$classifier][$genre] > $most) {
							$most = $collection["assertions"][$signal][$classifier][$genre];
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
										(weight: <?php echo sprintf("%.2f", $collection["assertions"][$leastmost[0]][$classifier][$genre]); ?>)
									</dd>
									<dt>Most</dt>
									<dd>
										<a href="<?php echo SITEROOT_WEB; ?>viewsignalresults?uri=<?php echo urlencode($leastmost[1]); ?>">
											<?php $info = signalinfo($leastmost[1]); ?>
											<em><?php echo htmlspecialchars($info["trackname"]); ?></em>
										by <?php echo htmlspecialchars($info["artistname"]); ?>
										</a>
										(weight: <?php echo sprintf("%.2f", $collection["assertions"][$leastmost[1]][$classifier][$genre]); ?>)
									</dd>
								</dl>
							</dd>
						<?php } ?>
					</dl>
				</dd>
			<?php } ?>
		</dl>

		<h3>Data table</h3>
		<?php if (count($collection["signals"]) > 100) { ?>
			<p>Not available for collections with over 100 signals</p>
		<?php } else { ?>
			<ul>
				<li><a class="fancybox" href="#datatable_<?php echo md5($collection["collectionuri"]); ?>">Show data table</a></li>
			</ul>
			<table id="datatable_<?php echo md5($collection["collectionuri"]); ?>" class="hidden datatable">
				<thead>
					<tr>
						<th rowspan="2">Signal</th>
						<?php foreach ($collection["classifier_genre"] as $classifier => $genres) { ?>
							<th colspan="<?php echo count($genres); ?>"><?php echo htmlspecialchars(classifiermapping($classifier)); ?></th>
						<?php } ?>
					</tr>
				</thead>
				<tbody>
					<?php
					$barcolumncount = 0;
					foreach ($collection["classifier_genre"] as $classifier => $genres)
						$barcolumncount += count($genres);
					$barcolumnwidth = 90 / $barcolumncount;
					?>
					<tr>
						<?php foreach ($collection["classifier_genre"] as $classifier => $genres) foreach ($genres as $index => $genre) { ?>
							<th width="<?php echo 90 / $barcolumncount; ?>%" title="<?php echo htmlspecialchars(uriendpart($genre)); ?>"><?php echo htmlspecialchars(substr(uriendpart($genre), 0, 3)) . "&hellip;"; ?></th>
						<?php } ?>
					</tr>
					<?php foreach ($collection["signals"] as $signal) { $signalinfo = signalinfo($signal); ?>
						<tr>
							<td title="<?php echo htmlspecialchars($signalinfo["artistname"]); ?> &ndash; <?php echo htmlspecialchars($signalinfo["trackname"]); ?>">
								<a href="<?php echo SITEROOT_WEB; ?>viewsignalresults?uri=<?php echo urlencode($signal); ?>">
									<?php echo htmlspecialchars($signalinfo["trackname"]); ?>
								</a>
							</td>
							<?php if (empty($collection["assertions"][$signal])) { ?>
								<td class="nodata" colspan="<?php echo $barcolumncount; ?>">
									No data
								</td>
							<?php } else foreach ($collection["classifier_genre"] as $classifier => $genres) { ?>
								<?php if (!isset($collection["assertions"][$signal][$classifier])) { ?>
									<td colspan="<?php echo count($genres); ?>">No data</td>
								<?php } else foreach ($genres as $genre) { ?>
									<?php if (isset($collection["assertions"][$signal][$classifier][$genre])) { ?>
										<td width="<?php echo 90 / $barcolumncount; ?>%" title="<?php echo $collection["assertions"][$signal][$classifier][$genre]; ?>">
											<div class="barcontainer">
												<div class="bar" style="width: <?php echo 100 * $collection["assertions"][$signal][$classifier][$genre] / $collection["classifier_maxweight"][$classifier]; ?>%;"></div>
												<div class="weight"><?php echo sprintf("%.2f", $collection["assertions"][$signal][$classifier][$genre]); ?></div>
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
				</tbody>
			</table>
		<?php } ?>
	<?php } //data available ?>
	<?php if (count($collections) > 1) { ?>
		</div><!--cell-->
	<?php } ?>
<?php } // foreach collection ?>
<?php if (count($collections) > 1) { ?>
	</div></div><!--cols, scroll-->
<?php } ?>

<?php
include "htmlfooter.php";
?>
