<?php

if (!isset($_REQUEST["uri"]))
	badrequest("no signal id specified");

// set up results endpoint
require_once SITEROOT_LOCAL . "include/arc/ARC2.php";
$config = array("remote_store_endpoint" => ENDPOINT_RESULTS);
$store = ARC2::getRemoteStore($config);

// get URI of frame data
$query = prefix("off") . "SELECT * WHERE { ?framedata off:subject <" . $_REQUEST["uri"] . "> }";
$row = $store->query($query, "row");

if (empty($row))
	die("frame data not found");

$file = fopen($row["framedata"], "r");

$model = null;
$data = array();
while ($line = trim(fgets($file))) {
	if (preg_match('%^#model: %', $line)) {
		// sort previous model's subarray by genre
		if (!is_null($model))
			ksort($data[$model]);

		// new model
		$model = substr($line, 8);
		$data[$model] = array();

		// get column names
		$line = trim(fgets($file));
		if ($line[0] != "#")
			die("expected field URIs line after model line");
		$uris = explode(",", substr($line, 1));

		// structure for series data
		foreach ($uris as $uri)
			$data[$model][$uri] = array();
	}

	// skip blank lines
	if (empty($line))
		continue;

	// exit if we start getting data but have no model
	if (is_null($model))
		die("expected model line");

	// store values
	$values = array_map("floatval", explode(",", $line));
	foreach ($uris as $index => $uri)
		$data[$model][$uri][] = $values[$index];
}

ksort($data);
fclose($file);

if (empty($data))
	die("no data");

$signalinfo = signalinfo($_REQUEST["uri"]);

$title = "Results for " . $signalinfo["trackname"] . " by " . $signalinfo["artistname"];
include "htmlheader.php";
?>
<h2>Results for <em><?php echo htmlspecialchars($signalinfo["trackname"]); ?></em> by <?php echo htmlspecialchars($signalinfo["artistname"]); ?></h2>

<div class="trythis collapsed">
	<div class="content">
		<p>You're now viewing any results which are available for a particular signal.</p>
		<p>Below is basic information about the track such as its name and artist, and information about how each classifier categorized it.</p>
		<p>Also availiable are links to each source of audiofiles which could be found through its linked data.</p>
		<p>If you scroll down you'll see some graphs showing how each classifier categorized the signal over time. Scroll down a little further to choose one of the available sources and listen to the song, seeing the graphs updated as the song plays.</p>
		<p>If you're lucky you'll see any external information which could be found on DBpedia and BBC music about this artist.</p>
		<p>Finally external data from DBpedia and BBC Music for artists of the same genre as this song (according to the classifiers) is shown.</p>
	</div>
</div>

<div class="cols">
	<div class="cell">
		<h3>Signal information</h3>
		<dl>
			<dt>Name</dt>
			<dd>
				<?php echo htmlspecialchars($signalinfo["trackname"]); ?>
				<?php echo urilink($_REQUEST["uri"], "signal URI"); ?>
				<?php echo urilink($signalinfo["track"], "track URI"); ?>
			</dd>

			<dt>Artist</dt>
			<dd>
				<?php echo htmlspecialchars($signalinfo["artistname"]); ?>
				<?php echo urilink($signalinfo["artist"]); ?>
			</dd>

			<dt>Location</dt>
			<dd>
				<?php echo htmlspecialchars(iso3166toname(substr($signalinfo["country"], -2))); ?>
				<?php echo urilink($signalinfo["country"]); ?>
			</dd>

			<dt>Record</dt>
			<dd>
				<?php echo htmlspecialchars($signalinfo["recordname"]); ?>
				<?php echo urilink($signalinfo["record"]); ?>
				(<?php echo date("Y-m-d", strtotime($signalinfo["recorddate"])); ?>),
				track <?php echo $signalinfo["tracknumber"]; ?>
			</dd>

			<?php if (!empty($signalinfo["tags"])) { ?>
				<dt>Record tags</dt>
				<dd>
					<ul>
						<?php foreach ($signalinfo["tags"] as $tag) { ?>
							<li>
								<?php echo htmlspecialchars(uriendpart($tag["tag"])); ?>
								<?php echo urilink($tag["tag"]); ?>
							</li>
						<?php } ?>
					</ul>
				</dd>
			<?php } ?>

			<?php if (!empty($signalinfo["availableas"])) { ?>
				<dt>Available as</dt>
				<dd>
					<ul>
						<?php foreach ($signalinfo["availableas"] as $aa) { ?>
							<li>
								<a href="<?php echo htmlspecialchars($aa["availableas"]); ?>">
									<?php echo htmlspecialchars($aa["availableas"]); ?>
								</a>
							</li>
						<?php } ?>
					</ul>
				</dd>
			<?php } ?>
		</dl>
	</div>

	<div class="cell">
		<h3>Overall classification</h3>
		<?php
		$classifier_genre_weight = array();
		foreach ($store->query(prefix(array_keys($ns)) . "
			SELECT * WHERE {
				?association
					sim:subject <" . $_REQUEST["uri"] . "> ;
					sim:method ?method ;
					sim:weight ?weight ;
					sim:object ?musicgenre .
				?method pv:usedGuideline ?classifier
			}
			ORDER BY ?classifier ?musicgenre
		", "rows") as $row) {
			if (!isset($classifier_genre_weight[$row["classifier"]]))
				$classifier_genre_weight[$row["classifier"]] = array();
			$classifier_genre_weight[$row["classifier"]][$row["musicgenre"]] = floatval($row["weight"]);
		}
		?>
		<dl class="single">
			<?php foreach ($classifier_genre_weight as $classifier => $genre_weight) { ?>
				<dt><?php echo htmlspecialchars(classifiermapping($classifier)); ?></dt>
				<dd>
					<div id="genrepiechart_<?php echo md5($classifier); ?>" style="width: 500px; height: 300px;"></div>
					<?php
					$piedata = array();
					foreach ($genre_weight as $genre => $weight)
						$piedata[] = array("label" => uriendpart($genre), "data" => $weight);
					?>
					<script type="text/javascript">
						$.plot($("#genrepiechart_<?php echo md5($classifier); ?>"), <?php echo json_encode($piedata); ?>, {series:{pie:{show:true}}});
					</script>
				</dd>
			<?php } ?>
		</dl>
	</div>
</div>

<h3>Classification over time</h3>
<script type="text/javascript">
	var graphs = {};
</script>
<dl>
	<?php foreach ($data as $classifier => $genres) { ksort($genres); ?>
		<dt><?php echo htmlspecialchars(classifiermapping($classifier)); ?></dt>
		<dd>
			<ul id="legend_<?php echo md5($classifier); ?>" class="serieslist">
				<?php foreach ($genres as $genre => $framedata) { ?>
					<li><label for="framedatachart_<?php echo md5($classifier); ?>_series_<?php echo md5($genre); ?>">
						<input class="framedatachart_<?php echo md5($classifier); ?>_series" type="checkbox" id="framedatachart_<?php echo md5($classifier); ?>_series_<?php echo md5($genre); ?>" name="framedatachart_<?php echo md5($classifier); ?>_series_<?php echo md5($genre); ?>" checked="checked">
						<?php echo htmlspecialchars(uriendpart($genre)); ?>
					</label></li>
				<?php } ?>
			</ul>
			<div id="framedatachart_<?php echo md5($classifier); ?>" style="width: 550px; height: 230px;"></div>
			<script type="text/javascript">
				graphs["<?php echo md5($classifier); ?>"] = {};
				<?php
				$datasets = array();
				foreach ($genres as $genre => $framedata) {
					$pairs = array();
					foreach ($framedata as $i => $v)
						$pairs[] = array($i, $v);
					$datasets[md5($genre)] = array(
						"label" => uriendpart($genre),
						"data" => $pairs,
					);
				}
				?>
				graphs["<?php echo md5($classifier); ?>"].series = <?php echo json_encode($datasets); ?>;
				var i = 0;
				$("input.framedatachart_<?php echo md5($classifier); ?>_series").click(function() {
					if (!$("#audioplayer").jPlayer("getData", "diag.isPlaying"))
						plotgraph("<?php echo md5($classifier); ?>", null, null);
				});
				var graph = plotgraph("<?php echo md5($classifier); ?>", null, null);
				var series = graph.getData();
				$("#legend_<?php echo md5($classifier); ?> li").each(function(i, e) {
					$(this).css("border-left", "1.5em solid " + series[i].color);
				});
			</script>
		</dd>
	<?php } ?>
</dl>

<h3>Listen</h3>
<?php $audiosources = audiosources($_REQUEST["uri"]); if (empty($audiosources)) { ?>
	<p>No audio sources were found</p>
<?php } else { ?>
	<h4>Choose audio source</h4>
	<p>These audio sources were found from the "availableas" links</p>
	<ul id="audiochooser">
		<?php $first = true; foreach ($audiosources as $source) { ?>
			<li><label>
				<input type="radio" name="audiosource" value="<?php echo htmlspecialchars($source); ?>"<?php if ($first) { $first = false; ?> checked="checked"<?php } ?>>
				<?php echo htmlspecialchars($source); ?>
			</label></li>
		<?php } ?>
	</ul>

	<h4>Audio controls</h4>
	<div id="audioplayer"></div>
	<div class="jp-single-player">
		<div class="jp-interface">
			<ul class="jp-controls">
				<li><a href="#" id="jplayer_play" class="jp-play" tabindex="1">play</a></li>
				<li><a href="#" id="jplayer_pause" class="jp-pause" tabindex="1">pause</a></li>
				<li><a href="#" id="jplayer_stop" class="jp-stop" tabindex="1">stop</a></li>
				<li><a href="#" id="jplayer_volume_min" class="jp-volume-min" tabindex="1">min volume</a></li>
				<li><a href="#" id="jplayer_volume_max" class="jp-volume-max" tabindex="1">max volume</a></li>
			</ul>
			<div class="jp-progress">
				<div id="jplayer_load_bar" class="jp-load-bar">
					<div id="jplayer_play_bar" class="jp-play-bar"></div>
				</div>
			</div>
			<div id="jplayer_volume_bar" class="jp-volume-bar">
				<div id="jplayer_volume_bar_value" class="jp-volume-bar-value"></div>
			</div>
			<div id="jplayer_play_time" class="jp-play-time"></div>
			<div id="jplayer_total_time" class="jp-total-time"></div>
		</div>
	</div>
	<script type="text/javascript">
		$(document).ready(function() {
			$("#audiochooser input").click(function(e) {
				$("#audioplayer").jPlayer("setFile", $(this).attr("value"));
			});
			$("#audioplayer").jPlayer({
				swfPath: "<?php echo SITEROOT_WEB; ?>include/jquery.jplayer",
				nativeSupport: true,
				preload: "auto",
				errorAlerts: true,
				warningAlerts: true,
				ready: function() {
					this.element.jPlayer("setFile", "<?php echo $audiosources[0]; ?>");
					this.element.jPlayer("onProgressChange", updatechart);
				}
			});
		});
		function updatechart(loadPercent, playedPercentRelative, playedPercentAbsolute, playedTime, totalTime) {
			for (var md5sum in graphs) {
				if ($("#audioplayer").jPlayer("getData", "diag.isPlaying"))
					plotgraph(md5sum, playedTime / 1000, totalTime / 1000);
				else
					plotgraph(md5sum, null, totalTime / 1000);
			}
		};
	</script>
<?php } ?>

<h3>External links for this artist</h3>
<?php
$bbcuri = bbcuri($signalinfo["artist"]);
if ($bbcuri === false) { ?>
	<p>This artist doesn't appear to be featured on BBC Music.</p>
<?php } else {
	$bbcinfo = bbcinfo($bbcuri);
	if (empty($bbcinfo)) { ?>
		<p>None of the information seeked was found on BBC Music.</p>
	<? } else { ?>
		<div class="bbcdata">
			<?php if (isset($bbcinfo["image"])) { ?>
				<img class="bbcimage" src="<?php echo htmlspecialchars($bbcinfo["image"]); ?>">
			<?php } ?>
			<ul>
				<li><a href="<?php echo htmlspecialchars($bbcuri); ?>">BBC Music page</a> <?php echo urilink($bbcuri, "URI of this artist at BBC Music"); ?></li>
				<?php if (isset($bbcinfo["comment"])) { ?>
					<li><em><?php echo htmlspecialchars($bbcinfo["comment"]); ?></em></li>
				<?php }
				if (isset($bbcinfo["homepage"])) { ?>
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
			<div class="clearer"></div>
		</div>
	<?php }
}
?>

<h3>External links for the heaviest weighted genre</h3>
<dl class="single">
	<?php foreach ($classifier_genre_weight as $classifier => $genre_weight) {
		arsort($genre_weight, SORT_NUMERIC);
		$heavy = array_shift(array_keys($genre_weight));
		?>
		<dt><?php echo htmlspecialchars(classifiermapping($classifier)); ?></dt>
		<dd>
			<p>Most heavily weighted genre: <strong><?php echo htmlspecialchars(uriendpart($heavy)); ?></strong> <?php echo urilink($heavy); ?></p>
			<?php
			$country = true;
			$artists = dbpediaartists($heavy, $signalinfo["country"]);
			if (empty($artists)) {
				$country = false;
				$artists = dbpediaartists($heavy);
			}
			?>
			<h4>Some random artists from the same <?php if ($country) echo "country and "; ?>genre:</h4>
			<p class="hint">Data from DBpedia</p>
			<?php if (empty($artists)) { ?>
				<p>No artists matching this genre were found in DBpedia.</p>
			<?php } else { ?>
				<ul class="bbcdata">
					<?php $a = array_rand($artists, min(count($artists), 10)); if (!is_array($a)) $a = array($a); foreach ($a as $k) { ?>
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
									<?php }
									if (isset($bbcinfo["homepage"])) { ?>
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
					<?php } ?>
				</ul>
				<div class="clearer"></div>
			<?php } ?>
		</dd>
	<?php } ?>
</dl>

<?php
include "htmlfooter.php";
?>
