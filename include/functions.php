<?php

// exit with various statuses
function badrequest($message = "bad request", $mimetype = "text/plain") {
	header("Content-Type: $mimetype", true, 400);
	if (is_array($message))
		foreach($message as $m)
			echo "- " . $m . "\n";
	else
		echo $message . "\n";
	exit;
}
function notfound($message = "not found", $mimetype = "text/plain") {
	header("Content-Type: $mimetype", true, 404);
	echo $message . "\n";
	exit;
}
function servererror($message = "internal server error", $mimetype = "text/plain") {
	header("Content-Type: $mimetype", true, 500);
	echo $message . "\n";
	exit;
}
function notacceptable($message = "not acceptable", $mimetype = "text/plain") {
	header("Content-Type: $mimetype", true, 406);
	echo $message . "\n";
	exit;
}
function multiplechoices($location = null, $message = "multiple choices -- be specific about what you accept", $mimetype = "text/plain") {
	header("Content-Type: application/json", true, 300);
	if (!is_null($location))
		header("Location: $location");
	echo $message . "\n";
	exit;
}

// array_filter recursively
function array_filter_recursive($input, $callback) {
	$output = array();
	foreach ($input as $key => $value) {
		if (is_array($value))
			$output[$key] = array_filter_recursive($value, $callback);
		else
			$output[$key] = call_user_func($callback, $value);
	}
	return $output;
}

// if magic quotes get/post/cookie is on, undo it by stripping slashes from each
function unmagic() {
	if (get_magic_quotes_gpc()) {
		$_GET = array_filter_recursive($_GET, "stripslashes");
		$_POST = array_filter_recursive($_POST, "stripslashes");
		$_COOKIE = array_filter_recursive($_COOKIE, "stripslashes");
	}
}

// redirect to another URL
function redirect($destination = null, $code = 301) {
	// redirect to current URI by default
	if (is_null($destination))
		$destination = $_SERVER["REQUEST_URI"];

	// deal with non-absolute URLs
	if ($destination[0] == "/")
		// assume $destination started with the siteroot -- prepend domain
		$destination = "http://" . $_SERVER["HTTP_HOST"] . $destination;
	else if (!preg_match('%^https?://%', $destination))
		// assume relative to current request URI
		$destination = "http://" . $_SERVER["HTTP_HOST"] . dirname($_SERVER["REQUEST_URI"]) . "/" . $destination;

	session_write_close();
	header("Location: " . $destination, true, $code);

	// give some HTML or plain text advising the user of the new URL should 
	// their user agent not redirect automatically
	switch (preferredtype(array("text/plain", "text/html"))) {
		case "text/html":
			header("Content-Type: text/html");
			?>
			<a href="<?php echo htmlspecialchars($destination); ?>">Redirect to <?php echo htmlspecialchars($destination); ?></a>
			<?php
			break;
		case "text/plain":
		default:
			header("Content-Type: text/plain");
			echo "Redirect to $location\n";
			break;
	}
	exit;
}

// return the user agent's preferred accepted type, given a list of available 
// types. or return true if there is no preference or false if no available type 
// is acceptable
function preferredtype($types = array("text/html")) {
	$acceptstring = strtolower($_SERVER["HTTP_ACCEPT"]);

	// if there's no accept string that's equivalent to */* -- no preference
	if (empty($acceptstring))
		return true;

	// build an array of mimetype to score, sort it descending
	$atscores = array();
	$accept = preg_split("/\s*,\s*/", $acceptstring);
	foreach ($accept as $part) {
		if (strpos($part, ";") !== false) {
			$type = explode(";", $part);
			$score = explode("=", $type[1]);
			$atscores[$type[0]] = $score[1];
		} else
			$atscores[$part] = 1;
	}
	arsort($atscores);

	// return the first match of accepted to offered, if any
	foreach ($atscores as $wantedtype => $score)
		if (in_array($wantedtype, $types))
			return $wantedtype;

	// no specific type accepted is offered -- look for type/*
	$allsubtypesof = array();
	foreach ($atscores as $wantedtype => $score) {
		$typeparts = explode("/", $wantedtype);
		if ($typeparts[1] == "*")
			$allsubtypesof[$typeparts[0]] = $score;
	}
	arsort($allsubtypesof);

	// match against offered types
	foreach ($allsubtypesof as $accepted => $score)
		foreach ($types as $offered)
			if (preg_replace('%(.*)/.*$%', '\1', $offered) == $accepted)
				return $offered;

	// if they accept */*, return true (no preference)
	if (in_array("*/*", array_keys($atscores)))
		return true;

	// return false -- we don't offer any accepted type
	return false;
}

// return a Sparql PREFIX string, given a namespace key from the global $ns 
// array, or many such PREFIX strings for an array of such keys
function prefix($n) {
	global $ns;
	if (!is_array($n))
		$n = array($n);
	$ret = "";
	foreach ($n as $s)
		$ret .= "PREFIX $s: <" . $ns[$s] . ">\n";
	return $ret;
}

// return results of a Sparql query to Jamendo
// maxage is the number of seconds old an acceptable cached result can be 
// (default one day, 0 means it must be collected newly. false means must be 
// collected newly and the result will not be stored. true means use cached 
// result however old it is)
// type is passed straight through to Arc
function queryjamendo($query, $maxage = true/*604800/*a week*//*86400/*1 day*/, $type = "rows") {
	$cachefile = SITEROOT_LOCAL . "cache/" . md5($query . $type);

	// collect from cache if available and recent enough
	if ($maxage === true && file_exists($cachefile) || $maxage !== false && $maxage > 0 && file_exists($cachefile) && time() < filemtime($cachefile) + $maxage)
		return unserialize(file_get_contents($cachefile));

	// cache is not to be used or cached file is out of date. query database
	require_once SITEROOT_LOCAL . "include/arc/ARC2.php";
	$config = array(
		"remote_store_endpoint" => ENDPOINT_JAMENDO,
		"reader_timeout" => 120,
	);
	$result = ARC2::getRemoteStore($config)->query($query, $type);

	// store result unless caching is switched off
	if ($maxage !== false)
		file_put_contents($cachefile, serialize($result));

	return $result;
}

// get a country name based on ISO-3166 two-character country code
function iso3166toname($cc) {
	static $iso3166 = null;
	if (is_null($iso3166)) {
		$iso3166 = array();
		foreach (file(SITEROOT_LOCAL . "data/iso3166_en.txt") as $line) {
			$parts = explode(";", $line);
			$iso3166[$parts[0]] = $parts[1];
		}
	}
	if (array_key_exists($cc, $iso3166))
		return $iso3166[$cc];
	else
		return false;
}

// get information about a signal
function signalinfo($signal) {
	$result = queryjamendo(prefix(array_keys($GLOBALS["ns"])) . "
		SELECT * WHERE {
			<$signal>
				mo:published_as ?track .
			?track
				dc:title ?trackname ;
				mo:track_number ?tracknumber .
			?record
				mo:track ?track ;
				a mo:Record ;
				foaf:maker ?artist ;
				dc:date ?recorddate ;
				dc:title ?recordname .
			?artist
				a mo:MusicArtist ;
				foaf:name ?artistname ;
				foaf:based_near ?basednear .
			OPTIONAL { ?basednear geo:inCountry ?country . }
		}
	");

	if (empty($result))
		return false;

	$tags = queryjamendo(prefix(array_keys($GLOBALS["ns"])) . "
		SELECT * WHERE {
			<" . $result[0]["record"] . "> tags:taggedWithTag ?tag .
		}
		ORDER BY ?tag
	");
	$result[0]["tags"] = $tags;

	$javailableas = queryjamendo(prefix(array_keys($GLOBALS["ns"])) . "
		SELECT * WHERE {
			<" . $result[0]["track"] . "> mo:available_as ?availableas .
		}
	");

	require_once SITEROOT_LOCAL . "include/arc/ARC2.php";
	$store = ARC2::getRemoteStore(array("remote_store_endpoint" => ENDPOINT_REPOSITORY));
	$ravailableas = $store->query(prefix("mo") . "
		SELECT * WHERE {
			<" . $result[0]["track"] . "> mo:available_as ?availableas .
		}
	", "rows");
	$result[0]["availableas"] = array_merge($javailableas, $ravailableas);

	foreach ($result[0]["availableas"] as $aa) {
		if (strpos($aa["availableas"], "http://repository.nema.ecs.soton.ac.uk") === 0) {
			$result[0]["mp3"] = $aa["availableas"] . ".mp3";
			break;
		}
	}

	return $result[0];
}

function uriendpart($string) {
	return preg_replace('%.*[/#](.*?)[/#]?%', '\1', $string);
}

function urilink($uri, $text = "URI") {
	return "[<a href=\"" . htmlspecialchars($uri) . "\">" . htmlspecialchars($text) . "</a>]";
}

// return a nicer name for a classifier URI if we have one, otherwise the URI
function classifiermapping($uri) {
	static $map = array(
		"http://results.nema.ecs.soton.ac.uk/classifiers/BlinkieGenreSupportVectorVersion2.ser0.serial"
			=> "Genre Support Vector version 2",
		"http://results.nema.ecs.soton.ac.uk/classifiers/BlinkieGenreJ48DecisionTree.ser0.serial"
		=> "Genre J48 Decision Tree",
	);
	if (array_key_exists($uri, $map))
		return $map[$uri];
	return $uri;
}

// query dbpedia for artists and their locations given a genre and optionally a 
// country URI
function dbpediaartists($genreuri, $countryuri = null) {
	require_once SITEROOT_LOCAL . "include/arc/ARC2.php";
	$store_dbpedia = ARC2::getRemoteStore(array("remote_store_endpoint" => ENDPOINT_DBPEDIA));
	$query = prefix(array("dbpedia-owl", "foaf")) . "
		SELECT ?artist ?artistname ?place ?placename WHERE {
			?artist
				dbpedia-owl:genre <$genreuri> .
			{
				?artist
					a dbpedia-owl:Band ;
					dbpedia-owl:hometown ?place .
			} UNION {
				?artist
					a dbpedia-owl:MusicalArtist ;
					dbpedia-owl:birthPlace ?place .
			}
			?artist
				foaf:name ?artistname .
			?place
				foaf:name ?placename .
		}
	";
	$dbartists = $store_dbpedia->query($query, "rows");
	if (empty($dbartists))
		return array();

	// weed out duplicate artists -- only want one place for each
	$artisturis = array();
	$dbartists2 = array();
	foreach ($dbartists as $dbartist) {
		if (!in_array($dbartist["artist"], $artisturis)) {
			$artisturis[] = $dbartist["artist"];
			$dbartists2[] = $dbartist;
		}
	}
	$dbartists = $dbartists2;

	if (is_null($countryuri))
		return $dbartists;

	// array of places
	$places = array();
	foreach ($dbartists as $dbartist)
		$places[] = $dbartist["place"];
	sort($places);
	$places = array_unique($places);

	// ask geonames for the dbpedia place URIs from the query above which are in 
	// the country given
	$subqueries = array();
	foreach ($places as $place) $subqueries[] = "{
		?feat
			owl:sameAs <$place> ;
			owl:sameAs ?same ;
			geo:inCountry <" . $countryuri . "> .
	}";
	$query = prefix(array("owl", "geo")) . "
		SELECT * WHERE {
			" . implode(" UNION ", $subqueries) . "
		}
	";
	$store_geonames = ARC2::getRemoteStore(array("remote_store_endpoint" => ENDPOINT_GEONAMES));
	$result = $store_geonames->query($query, "rows");

	$places_in_country = array();
	foreach ($result as $geoplace)
		$places_in_country[] = $geoplace["same"];

	$dbartists_in_country = array();
	foreach ($dbartists as $dbartist)
		if (in_array($dbartist["place"], $places_in_country))
			$dbartists_in_country[] = $dbartist;

	return $dbartists_in_country;
}

?>
