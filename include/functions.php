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

	return $result[0];
}

function audiosources($signal) {
	$signalinfo = signalinfo($signal);

	$audiosources = array();
	foreach ($signalinfo["availableas"] as $aa) {
		$source = findmp3($aa["availableas"]);
		if ($source !== false)
			$audiosources[] = $source;
	}
	$audiosources = array_unique($audiosources);

	// rank by URL text contents
	usort($audiosources, "rankmp3urls");
	$audiosources = array_reverse($audiosources);

	return $audiosources;
}

// follow a URI and try to get an MP3 from it
function findmp3($uri, $depth = 0) {
	// cancel if we're recursing too deeply
	if ($depth > 5)
		return false;

	$c = curl_init();
	curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($c, CURLOPT_CUSTOMREQUEST, "HEAD");
	curl_setopt($c, CURLOPT_HEADER, true);
	curl_setopt($c, CURLOPT_NOBODY, true);
	curl_setopt($c, CURLOPT_URL, $uri);
	curl_setopt($c, CURLOPT_HTTPHEADER, array(
		//       MP3         XSPF playlist               M3U playlist           scrapeable    XML?                 other audio, could be playlist like M3U
		"Accept: audio/mpeg, application/xspf+xml;q=0.8, audio/x-mpegurl;q=0.8, text/*;q=0.3, application/*;q=0.2, audio/*,q=0.1",
	));

	$headers = explode("\r\n", curl_exec($c));
	$code = curl_getinfo($c, CURLINFO_HTTP_CODE);

	// is this a redirection?
	$redirect = $code >= 300 && $code < 400;

	// was it ok?
	$ok = $code >= 200 && $code < 300;

	foreach ($headers as $header) {
		if (!preg_match('%^.+:.+$%', $header))
			continue;
		list($key, $value) = preg_split('%\s*:\s*%', $header, 2);
		$key = strtolower($key);

		// if we have a new location and a redirect code, recurse
		if ($key == "location" && $redirect)
			return findmp3($value, $depth + 1);

		// look for Content-Type header
		if ($key == "content-type") {
			// remove parameters
			$contenttype = preg_replace('%^([^\s;]+/[^\s;]+).*$%', '\1', $value);
		}
	}

	if (!$ok)
		return false;

	if ($contenttype == "audio/mpeg")
		// MP3
		return $uri;

	// get the contents
	curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($c, CURLOPT_CUSTOMREQUEST, "GET");
	curl_setopt($c, CURLOPT_HEADER, false);
	curl_setopt($c, CURLOPT_NOBODY, false);

	$contents = curl_exec($c);
	$code = curl_getinfo($c, CURLINFO_HTTP_CODE);

	// exit if not OK
	if ($code < 200 || $code >= 300)
		return false;

	$urls = array();
	if ($contenttype == "application/xspf+xml") {
		// XSPF playlist
		$xml = new SimpleXMLElement($contents);
		foreach ($xml->trackList->track as $track)
			$urls[] = (string) $track->location;
	} else if ($contenttype == "audio/x-mpegurl") {
		// M3U playlist
		$lines = preg_split('%\s*(\r\n|\r|\n)\s*%', trim($contents));
		foreach ($lines as $line)
			// collect HTTP URL lines
			if (preg_match('%^http%', $line))
				$urls[] = $line;
	} else {
		// other -- scrape for URLs
		preg_match_all('/(([\w]+:)?\/\/)?(([\d\w]|%[a-fA-f\d]{2,2})+(:([\d\w]|%[a-fA-f\d]{2,2})+)?@)?([\d\w][-\d\w]{0,253}[\d\w]\.)+[\w]{2,4}(:[\d]+)?(\/([-+_~.\d\w]|%[a-fA-f\d]{2,2})*)*(\?(&amp;?([-+_~.\d\w]|%[a-fA-f\d]{2,2})=?)*)?(#([-+_~.\d\w]|%[a-fA-f\d]{2,2})*)?/', $contents, $urls);
		$urls = $urls[0];
	}

	// rank by URL text contents
	usort($urls, "rankmp3urls");
	$urls = array_reverse($urls);

	if (empty($urls))
		return false;

	// return the most likely contender
	foreach ($urls as $url) {
		$r = findmp3($url, $depth + 1);
		if ($r !== false)
			return $r;
	}
}

function mp3urlrank($url) {
	$rank = 0;
	if (preg_match('%\.mp3($|\?)%i', $url))
		$rank += 10;
	else if (preg_match('%mp3%i', $url))
		$rank += 5;
	if (preg_match('%repository.nema.ecs.soton.ac.uk%i', $url))
		$rank += 5;
	if (preg_match('%listen%i', $url))
		$rank += 1;
	if (preg_match('%stream%i', $url))
		$rank += 1;
	if (preg_match('%music%i', $url))
		$rank += 1;
	if (preg_match('%audio%i', $url))
		$rank += 1;
	if (preg_match('%signal%i', $url))
		$rank += 1;
	if (preg_match('%(album|cover)art%i', $url))
		$rank -= 5;
	if (preg_match('%cover%i', $url))
		$rank -= 1;
	if (preg_match('%art%i', $url))
		$rank -= 1;
	if (preg_match('%licen[cs]e%i', $url))
		$rank -= 2;
	if (preg_match('%creativecommons\.org%', $url))
		$rank -= 10;
	if (preg_match('%imgjam\.com%', $url))
		$rank -= 10;

	return $rank;
}

function rankmp3urls($a, $b) {
	return mp3urlrank($a) - mp3urlrank($b);
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

// get BBC URI which is the sameAs the given DBpedia URI. return false if there 
// is none
function bbcuri($dbpediauri) {
	require_once SITEROOT_LOCAL . "include/arc/ARC2.php";

	$query = prefix("owl") . "
		SELECT * WHERE {
			?bbcuri owl:sameAs <" . $dbpediauri . "> .
			FILTER regex(str(?bbcuri), \"^http://www.bbc.co.uk/\") .
		}
	";
	$store = ARC2::getRemoteStore(array("remote_store_endpoint" => ENDPOINT_BBC));
	$result = $store->query($query, "rows");

	if (empty($result))
		return false;

	return $result[0]["bbcuri"];
}

// get info from the BBC, given a BBC URI
function bbcinfo($bbcuri) {
	require_once SITEROOT_LOCAL . "include/arc/ARC2.php";

	$store = ARC2::getRemoteStore(array("remote_store_endpoint" => ENDPOINT_BBC));

	$query = prefix(array_keys($GLOBALS["ns"])) . "
		SELECT * WHERE {
			OPTIONAL { <$bbcuri> rdfs:comment ?comment . }
			OPTIONAL { <$bbcuri> foaf:homepage ?homepage . }
			OPTIONAL { <$bbcuri> mo:image ?image . }
			OPTIONAL { <$bbcuri> mo:wikipedia ?wikipedia . }
			OPTIONAL { <$bbcuri> mo:musicbrainz ?musicbrainz . }
			OPTIONAL { <$bbcuri> mo:imdb ?imdb . }
			OPTIONAL { <$bbcuri> mo:myspace ?myspace . }
		}
	";
	$result = $store->query($query, "rows");

	if (empty($result))
		return array();
	return $result[0];
}

// get all associations for given array of signals
function getassociations($signals) {
	require_once SITEROOT_LOCAL . "include/arc/ARC2.php";

	// set up results endpoint
	$config = array("remote_store_endpoint" => ENDPOINT_RESULTS);
	$store = ARC2::getRemoteStore($config);

	// array for results
	$results = array();

	// build array of subqueries
	$subqueries = array();
	foreach ($signals as $signal) $subqueries[] = "{
		?genreassociation
			sim:subject <$signal> ;
			sim:subject ?signal ;
			sim:object ?musicgenre ;
			sim:weight ?weight ;
			sim:method ?associationmethod .
		?associationmethod
			pv:usedGuideline ?classifier .
	}";

	// make queries with maximum 128 subqueries each (arbitrary number but 256 
	// is too big and 4store baulks)
	while (!empty($subqueries)) {
		// get up to 128 remaining subqueries
		$sq = array();
		for ($i = 0; $i < 128 && !empty($subqueries); $i++)
			$sq[] = array_shift($subqueries);

		// query the endpoint, add results to results array
		$query = prefix(array("mo", "sim", "pv")) . "
			SELECT * WHERE {
				" . implode(" UNION ", $sq) . "
			}
			ORDER BY ?signal ?classifier ?musicgenre
		";
		$rows = $store->query($query, "rows");
		$results = array_merge($results, $rows);
	}

	return $results;
}

// recursively delete a directory and its contents
function rmrecursive($obj) {
	if (is_dir($obj)) {
		foreach (scandir($obj) as $o)
			if ($o != "." && $o != "..")
				rmrecursive("$obj/$o");
		rmdir($obj);
	} else
		unlink($obj);
}

?>
