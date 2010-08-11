<?php

//get all country codes associated with artists
$jamcountries = queryjamendo("
	" . prefix(array("geo", "mo", "foaf")) . "
	SELECT DISTINCT ?country
	WHERE {
		?a
			a mo:MusicArtist ;
			foaf:based_near ?basednear .
		?basednear
			geo:inCountry ?country .
	}
	ORDER BY ?country
");
$countries = array();
foreach ($jamcountries as $row) {
	$cc = substr($row["country"], -2);
	$countries[$cc] = iso3166toname($cc);
}
asort($countries);

if (!isset($_REQUEST["id"]))
	badrequest("No collection ID specified");
$collection = Collection::fromID($_REQUEST["id"]);
if (!$collection)
	badrequest("No collection found in session data for specified ID");

if (isset($_REQUEST["submit"])) {
	$collection->data = $_REQUEST;

	$collection->title(trim($_POST["title"]));
	$collection->description(trim($_POST["description"]));
	$collection->author(trim($_POST["author"]));

	$collection->clearfilters();

	// artist name
	if (isset($collection->data["filteractive_artistname"]) && isset($collection->data["artistname_regex"]) && !empty($collection->data["artistname_regex"]))
		$collection->addfilter("
			FILTER regex(str(?artistname), \"" . str_replace('"', '\"', $collection->data["artistname_regex"]) . "\"" . (isset($collection->data["artistname_ci"]) ? ", \"i\"" : "") . ") .
		");
	else
		unset($collection->data["filteractive_artistname"]);

	// artist country
	if (isset($collection->data["filteractive_artistcountry"]) && isset($collection->data["artistcountry_countrycode"]) && is_array($collection->data["artistcountry_countrycode"])) {
		$unions = array();
		foreach($collection->data["artistcountry_countrycode"] as $cc) {
			if (iso3166toname($cc) !== false)
				$unions[] = "{ ?basednear geo:inCountry <http://www.geonames.org/countries/#$cc> }";
		}
		$collection->addfilter(implode(" UNION ", $unions));
	} else
		unset($collection->data["filteractive_artistcountry"]);

	// record date
	if (isset($collection->data["filteractive_recorddate"])) {
		if (isset($collection->data["recorddate_from_active"]) && preg_match("%^[0-9]{4}-[0-9]{2}-[0-9]{2}$%", $collection->data["recorddate_from"]))
			$subfilters[] = "xsd:dateTime(?recorddate) >= \"" . $collection->data["recorddate_from"] . "T00:00:00Z\"^^xsd:dateTime";
		if (isset($collection->data["recorddate_before_active"]) && preg_match("%^[0-9]{4}-[0-9]{2}-[0-9]{2}$%", $collection->data["recorddate_before"]))
			$subfilters[] = "xsd:dateTime(?recorddate) < \"" . $collection->data["recorddate_before"] . "T00:00:00Z\"^^xsd:dateTime";
		if (!empty($subfilters))
			$collection->addfilter("
				FILTER (" . implode(" && ", $subfilters) . ") .
			");
		else
			unset($collection->data["filteractive_recorddate"]);
	}

	// record name
	if (isset($collection->data["filteractive_recordname"]) && isset($collection->data["recordname_regex"]) && !empty($collection->data["recordname_regex"]))
		$collection->addfilter("
			FILTER regex(str(?recordname), \"" . str_replace('"', '\"', $collection->data["recordname_regex"]) . "\"" . (isset($collection->data["recordname_ci"]) ? ", \"i\"" : "") . ") .
		", true);
	else
		unset($collection->data["filteractive_recordname"]);

	// record tag
	if (isset($collection->data["filteractive_recordtag"]) && isset($collection->data["recordtag_tag"]) && !empty($collection->data["recordtag_tag"]))
		$collection->addfilter("
			?record tags:taggedWithTag <http://dbtune.org/jamendo/tag/" . strtolower($collection->data["recordtag_tag"]) . "> .
		", true);
	else
		unset($collection->data["filteractive_recordtag"]);

	// track name
	if (isset($collection->data["filteractive_trackname"]) && isset($collection->data["trackname_regex"]) && !empty($collection->data["trackname_regex"]))
		$collection->addfilter("
			?track dc:title ?trackname .
			FILTER regex(str(?trackname), \"" . str_replace('"', '\"', $collection->data["trackname_regex"]) . "\"" . (isset($collection->data["trackname_ci"]) ? ", \"i\"" : "") . ") .
		", true);
	else
		unset($collection->data["filteractive_trackname"]);

	// track number
	if (isset($collection->data["filteractive_tracknumber"]) && isset($collection->data["tracknumber_number"]) && !empty($collection->data["tracknumber_number"]))
		$collection->addfilter("
			?track mo:track_number \"" . intval($collection->data["tracknumber_number"]) . "\"^^xsd:int .
		", true);
	else
		unset($collection->data["filteractive_tracknumber"]);

	$filters = $collection->filters();
	$collection->query(prefix(array("geo", "foaf", "mo", "tags", "dc", "xsd")) . "
		SELECT * WHERE {
			?artist
				a mo:MusicArtist ;
				foaf:name ?artistname ;
				foaf:based_near ?basednear .
			" . implode("\n\t\t", $collection->filters(array("record", "track", "trackname", "tracknumber", "recorddate", "recordname"), $filters)) . "
			OPTIONAL { ?basednear geo:inCountry ?country . }
			?record
				a mo:Record ;
				foaf:maker ?artist ;
				mo:track ?track ;
				dc:date ?recorddate ;
				dc:title ?recordname .
			" . implode("\n\t\t", $collection->filters(array("trackname", "tracknumber"), $filters)) . "
			?track
				dc:title ?trackname ;
				mo:track_number ?tracknumber .
			" . implode("\n\t\t", $collection->filters(array(), $filters)) . "
			?signal
				mo:published_as ?track .
		}
	");
}

ob_start();
?>
$(document).ready(function() {
	$("dl#filters > dt input[type=checkbox]").each(function() {
		if (!$(this).is(":checked"))
			$(this).parents("dt:first").next("dd").hide();
	});
	$("dl#filters > dt input[type=checkbox]").click(function(e) {
		if ($(this).is(":checked"))
			$(this).parents("dt:first").next("dd").show("fast");
		else
			$(this).parents("dt:first").next("dd").hide("fast");
		$(this).parents("li:first").find("dl").show("fast");
	});

	$("#results").hide();
	$("#toggleresults").click(function(e) {
		e.preventDefault();
		$("#results").toggle("fast");
	});
});
<?php
$headerjs = ob_get_clean();
include "htmlheader.php";

?>

<h2>New collection<?php if ($collection->title()) { ?> "<?php echo htmlspecialchars($collection->title()); ?>"<?php } ?></h2>

<div class="cols">
	<div class="cell">
		<h3>Build collection</h3>
		<form action="<?php echo SITEROOT_WEB; ?>editcollection/<?php echo $collection->id(); ?>" method="post">
			<dl>
				<dt><label for="title">Title</label></dt>
				<dd><input type="text" size="64" name="title" id="title"<?php if ($collection->title()) { ?> value="<?php echo htmlspecialchars($collection->title()); ?>"<?php } ?>></dd>

				<dt><label for="description">Description</label></dt>
				<dd><textarea cols="64" rows="4" name="description" id="description"><?php if ($collection->description()) echo htmlspecialchars($collection->description()); ?></textarea></dd>

				<dt><label for="author">Author</label></dt>
				<dd><input type="text" size="64" name="author" id="author"<?php if ($collection->author()) { ?> value="<?php echo htmlspecialchars($collection->author()); ?>"<?php } ?>></dd>

				<dt>Filters</dt>
				<dd>
					<dl id="filters">
						<dt>
							<label>
								<input type="checkbox" name="filteractive_artistname" id="filteractive_artistname" value="true"<?php if (isset($collection->data["filteractive_artistname"])) { ?> checked="checked"<?php } ?>>
								Artist name
							</label>
						</dt>
						<dd>
							<dl>
								<dt><label for="artistname_regex">Regex</label></dt>
								<dd><input type="text" size="64" name="artistname_regex" id="artistname_regex"<?php if (isset($collection->data["artistname_regex"])) { ?> value="<?php echo htmlspecialchars($collection->data["artistname_regex"]); ?>"<?php } ?>></dd>

								<dt><label for="artistname_ci">Case insensitive</label></dt>
								<dd><input type="checkbox" name="artistname_ci" id="artistname_ci" value="true"<?php if (isset($collection->data["artistname_ci"])) { ?> checked="checked"<?php } ?>></dd>
							</dl>
						</dd>
						<dt>
							<label>
								<input type="checkbox" name="filteractive_artistcountry" id="filteractive_artistcountry" value="true"<?php if (isset($collection->data["filteractive_artistcountry"])) { ?> checked="checked"<?php } ?>>
								Artist country
							</label>
						</dt>
						<dd>
							<dl>
								<dt><label for="artistcountry_countrycode">Country</label></dt>
								<dd>
									<select multiple="multiple" size="8" name="artistcountry_countrycode[]" id="artistcountry_countrycode">
										<?php foreach ($countries as $cc => $name) { ?>
											<option value="<?php echo htmlspecialchars($cc); ?>"<?php if (isset($collection->data["artistcountry_countrycode"]) && in_array($cc, $collection->data["artistcountry_countrycode"])) { ?> selected="selected"<?php } ?>><?php echo htmlspecialchars($name); ?></option>
										<?php } ?>
									</select>
								</dd>
							</dl>
						</dd>
						<dt>
							<label>
								<input type="checkbox" name="filteractive_recorddate" id="filteractive_recorddate" value="true"<?php if (isset($collection->data["filteractive_recorddate"])) { ?> checked="checked"<?php } ?>>
								Record date (YYYY-MM-DD strings)
							</label>
						</dt>
						<dd>
							<dl>
								<dt>
									<input type="checkbox" name="recorddate_from_active" id="recorddate_from_active"<?php if (isset($collection->data["recorddate_from_active"])) { ?> checked="checked"<?php } ?>>
									From
								</dt>
								<dd><input type="text" size="16" name="recorddate_from"<?php if (isset($collection->data["recorddate_from"])) { ?> value="<?php echo htmlspecialchars($collection->data["recorddate_from"]); ?>"<?php } ?>></dd>
								<dt>
									<input type="checkbox" name="recorddate_before_active" id="recorddate_before_active"<?php if (isset($collection->data["recorddate_before_active"])) { ?> checked="checked"<?php } ?>>
									Before
								</dt>
								<dd><input type="text" size="16" name="recorddate_before"<?php if (isset($collection->data["recorddate_before"])) { ?> value="<?php echo htmlspecialchars($collection->data["recorddate_before"]); ?>"<?php } ?>></dd>
							</dl>
						</dd>
						<dt>
							<label>
								<input type="checkbox" name="filteractive_recordname" id="filteractive_recordname" value="true"<?php if (isset($collection->data["filteractive_recordname"])) { ?> checked="checked"<?php } ?>>
								Record name
							</label>
						</dt>
						<dd>
							<dl>
								<dt><label for="recordname_regex">Regex</label></dt>
								<dd><input type="text" size="64" name="recordname_regex" id="recordname_regex"<?php if (isset($collection->data["recordname_regex"])) { ?> value="<?php echo htmlspecialchars($collection->data["recordname_regex"]); ?>"<?php } ?>></dd>

								<dt><label for="recordname_ci">Case insensitive</label></dt>
								<dd><input type="checkbox" name="recordname_ci" id="recordname_ci" value="true"<?php if (isset($collection->data["recordname_ci"])) { ?> checked="checked"<?php } ?>></dd>
							</dl>
						</dd>
						<dt>
							<label>
								<input type="checkbox" name="filteractive_recordtag" id="filteractive_recordtag" value="true"<?php if (isset($collection->data["filteractive_recordtag"])) { ?> checked="checked"<?php } ?>>
								Record tag
							</label>
						</dt>
						<dd>
							<dl>
								<dt><label for="recordtag_tag">Tag</label></dt>
								<dd><input type="text" size="64" name="recordtag_tag" id="recordtag_tag"<?php if (isset($collection->data["recordtag_tag"])) { ?> value="<?php echo htmlspecialchars($collection->data["recordtag_tag"]); ?>"<?php } ?>></dd>
							</dl>
						</dd>
						<dt>
							<label>
								<input type="checkbox" name="filteractive_trackname" id="filteractive_trackname" value="true"<?php if (isset($collection->data["filteractive_trackname"])) { ?> checked="checked"<?php } ?>>
								Track name
							</label>
						</dt>
						<dd>
							<dl>
								<dt><label for="trackname_regex">Regex</label></dt>
								<dd><input type="text" size="64" name="trackname_regex" id="trackname_regex"<?php if (isset($collection->data["trackname_regex"])) { ?> value="<?php echo htmlspecialchars($collection->data["trackname_regex"]); ?>"<?php } ?>></dd>

								<dt><label for="trackname_ci">Case insensitive</label></dt>
								<dd><input type="checkbox" name="trackname_ci" id="trackname_ci" value="true"<?php if (isset($collection->data["trackname_ci"])) { ?> checked="checked"<?php } ?>></dd>
							</dl>
						</dd>
						<dt>
							<label>
								<input type="checkbox" name="filteractive_tracknumber" id="filteractive_tracknumber" value="true"<?php if (isset($collection->data["filteractive_tracknumber"])) { ?> checked="checked"<?php } ?>>
								Track number
							</label>
						</dt>
						<dd>
							<dl>
								<dt><label for="tracknumber_number">Track number</label></dt>
								<dd><input type="text" size="4" name="tracknumber_number" id="tracknumber_number"<?php if (isset($collection->data["tracknumber_number"])) { ?> value="<?php echo htmlspecialchars($collection->data["tracknumber_number"]); ?>"<?php } ?>></dd>
							</dl>
						</dd>
					</dl>
				</dd>

				<dt>Submit</dt>
				<dd><input type="submit" name="submit" value="Update"></dd>
			</dl>
		</form>
	</div>

	<?php if (count($collection->filters()) > 0) { ?>
		<div class="cell" id="fullquery">
			<h3>Full query</h3>
			<pre id="query"><?php echo htmlspecialchars($collection->query()); ?></pre>
		</div>
	<?php } ?>
</div>

<?php if (count($collection->filters()) > 0) { ?>
	<h3>Results</h3>
	<?php
	$results_artists = array();
	$results_records = array();
	$results_countries = array();
	foreach ($collection->results() as $result) {
		if (isset($result["artist type"]) && $result["artist type"] == "uri")
			$results_artists[] = $result["artist"];
		if (isset($result["record type"]) && $result["record type"] == "uri")
			$results_records[] = $result["record"];
		if (isset($result["country type"]) && $result["country type"] == "uri")
			$results_countries[] = $result["country"];
	}
	$results_artists = array_unique($results_artists);
	$results_records = array_unique($results_records);
	$results_countries = array_unique($results_countries);
	?>
	<p>
		Found <?php echo count($collection->results()); ?> track<?php echo count($collection->results()) == 1 ? "" : "s"; ?>
		from <?php echo count($results_records); ?> record<?php echo count($results_records) == 1 ? "" : "s"; ?>
		by <?php echo count($results_artists); ?> artist<?php echo count($results_artists) == 1 ? "" : "s"; ?>
		from <?php echo count($results_countries); ?> countr<?php echo count($results_countries) == 1 ? "y" : "ies"; ?>.
	</p>
	<p><a href="#" id="toggleresults">Toggle results table</a></p>
	<table id="results" class="datatable">
		<tr>
			<th>Country</th>
			<th>Artist</th>
			<th>Record date</th>
			<th>Record</th>
			<th>Track number</th>
			<th>Track</th>
		</tr>
		<?php foreach ($collection->results() as $result) { ?>
			<tr>
				<td><?php if (isset($result["country type"]) && $result["country type"] == "uri") { ?>
					<a href="<?php echo htmlspecialchars($result["country"]); ?>"><?php echo htmlspecialchars(iso3166toname(substr($result["country"], -2))); ?></a>
				<?php } ?></td>
				<td><?php if (isset($result["artist type"]) && $result["artist type"] == "uri") { ?>
					<a href="<?php echo htmlspecialchars($result["artist"]); ?>"><?php echo htmlspecialchars($result["artistname"]); ?></a>
				<?php } ?></td>
				<td><?php if (isset($result["recorddate type"]) && $result["recorddate type"] == "literal") { ?>
					<?php echo htmlspecialchars(date("Y-m-d", strtotime($result["recorddate"]))); ?>
				<?php } ?></td>
				<td><?php if (isset($result["record type"]) && $result["record type"] == "uri") { ?>
					<a href="<?php echo htmlspecialchars($result["record"]); ?>"><?php echo htmlspecialchars($result["recordname"]); ?></a>
				<?php } ?></td>
				<td><?php if (isset($result["tracknumber type"]) && $result["tracknumber type"] == "literal") { ?>
					<?php echo htmlspecialchars($result["tracknumber"]); ?>
				<?php } ?></td>
				<td><?php if (isset($result["track type"]) && $result["track type"] == "uri") { ?>
					<a href="<?php echo htmlspecialchars($result["track"]); ?>"><?php echo htmlspecialchars($result["trackname"]); ?></a>
				<?php } ?></td>
			</tr>
		<?php } ?>
	</table>

<?php } ?>
<h3>Actions</h3>
<?php
$problems = array();
if (count($collection->results()) == 0)
	$problems[] = "Empty collection";
if (!$collection->title())
	$problems[] = "Title required";
if (!$collection->author())
	$problems[] = "Author required";
?>
<ul>
	<?php if (!empty($problems)) { ?>
		<li>
			<p>Can't publish yet:</p>
			<ul>
				<?php foreach ($problems as $problem) { ?>
					<li><?php echo htmlspecialchars($problem); ?></li>
				<?php } ?>
			</ul>
		</li>
	<?php } else { ?>
		<li>
			<a href="<?php echo SITEROOT_WEB; ?>publishcollection/<?php echo $collection->id(); ?>">Publish</a>
		</li>
	<?php } ?>
	<li>
		<p>Demo queries</p>
		<dl class="twocol">
			<?php foreach (array(
				array("Spain", 3000, "ES", "Spanish"),
				array("Poland", 1100, "PL", "Polish"),
				array("Turkey", 15, "TR", "Turkish"),
				array("Ireland", 15, "IE", "Irish"),
				array("Lithuania", 15, "LT", "Lithuanian"),
				array("Iceland", 20, "IS", "Icelandic"),
				array("Malta", 30, "MT", "Maltese"),
				array("Ukraine", 5, "UA", "Ukrainian"),
				array("Estonia", 3, "EE", "Estonian"),
			) as $country) { ?>
				<dt>Music from <?php echo $country[0]; ?></dt>
				<dd>
					<ul>
						<li><?php if ($country[1] >= 50) echo "Large"; else if ($country[1] >= 10) echo "Small"; else echo "Very small"; ?> set (~<?php echo $country[1]; ?>)</li>
						<li><?php if ($country[1] >= 50) echo "Present on demo external HDD"; else echo "Present on VM HDD"; ?></li>
						<li>
							<form action="<?php echo SITEROOT_WEB; ?>editcollection/<?php echo $collection->id(); ?>" method="post">
								<input type="hidden" name="author" value="demo">
								<input type="hidden" name="filteractive_artistcountry" value="true">
								<input type="hidden" name="artistcountry_countrycode[]" value="<?php echo $country[2]; ?>">
								<input type="hidden" name="description" value="A collection of Creative Commons-licensed music from <?php echo $country[3]; ?> artists">
								<input type="hidden" name="title" value="Music from <?php echo $country[0]; ?>">
								<input type="submit" name="submit" value="Populate">
							</form>
						</li>
					</ul>
				</dd>
			<?php } ?>
		</dl>
	</li>
</ul>

<?php
include "htmlfooter.php";
?>
