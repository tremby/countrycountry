<?php

// fake email to output
function fakemail($to, $subject, $message, $headers = "") {
	echo "To: $to\r\nSubject: $subject\r\n$headers\r\n\r\n$message";
	return true;
}

// exit with various statuses
function badrequest($message = "bad request", $mimetype = "text/plain") {
	header("Content-Type: $mimetype", true, 400);
	if (is_array($message))
		foreach ($message as $m)
			echo "- $m\n";
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
	if (is_array($message))
		foreach ($message as $m)
			echo "- $m\n";
	else
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
function ok($message = null, $mimetype = "text/plain") {
	if (is_null($message))
		header("Content-Type: text/plain", true, 204);
	else {
		header("Content-Type: $mimetype", true, 200);
		echo $message;
	}
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

// return results of a Sparql query
// maxage is the number of seconds old an acceptable cached result can be 
// (default one day, 0 means it must be collected newly. false means must be 
// collected newly and the result will not be stored. true means use cached 
// result however old it is)
// type is passed straight through to Arc
function sparqlquery($endpoint, $query, $type = "rows", $maxage = 86400/*1 day*/) {
	$cachedir = SITEROOT_LOCAL . "cache/" . md5($endpoint);

	if (!is_dir($cachedir))
		mkdir($cachedir) or die("couldn't make cache directory");

	$cachefile = $cachedir . "/" . md5($query . $type);

	// collect from cache if available and recent enough
	if ($maxage === true && file_exists($cachefile) || $maxage !== false && $maxage > 0 && file_exists($cachefile) && time() < filemtime($cachefile) + $maxage)
		return unserialize(file_get_contents($cachefile));

	// cache is not to be used or cached file is out of date. query endpoint
	require_once SITEROOT_LOCAL . "include/arc/ARC2.php";
	$config = array(
		"remote_store_endpoint" => $endpoint,
		"reader_timeout" => 120,
		"ns" => $GLOBALS["ns"],
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
	$result = sparqlquery(ENDPOINT_JAMENDO, prefix(array_keys($GLOBALS["ns"])) . "
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

	$tags = sparqlquery(ENDPOINT_JAMENDO, prefix(array_keys($GLOBALS["ns"])) . "
		SELECT * WHERE {
			<" . $result[0]["record"] . "> tags:taggedWithTag ?tag .
		}
		ORDER BY ?tag
	");
	$result[0]["tags"] = $tags;

	$javailableas = sparqlquery(ENDPOINT_JAMENDO, prefix(array_keys($GLOBALS["ns"])) . "
		SELECT * WHERE {
			<" . $result[0]["track"] . "> mo:available_as ?availableas .
		}
	");

	$ravailableas = sparqlquery(ENDPOINT_REPOSITORY, prefix("mo") . "
		SELECT * WHERE {
			<" . $result[0]["track"] . "> mo:available_as ?availableas .
		}
	");
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

	// if it is an MP3 return it
	// or if we had an "unauthorized" code return it anyway in the blind hope 
	// that it points to an MP3
	if ($contenttype == "audio/mpeg" || $code == 401)
		return $uri;

	if (!$ok)
		return false;

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
	return "<a href=\"" . htmlspecialchars($uri) . "\" title=\"" . htmlspecialchars($text) . "\"><img src=\"" . SITEROOT_WEB . "images/uri.png\" alt=\"URI\"></a>";
}

// return a nicer name for a classifier URI if we have one, otherwise the URI
function classifiermapping($uri) {
	static $map = array(
		"http://results.nema.ecs.soton.ac.uk/classifiers/BlinkieGenreSupportVectorVersion2.ser0.serial" => "Genre Support Vector version 2",
		"http://results.nema.ecs.soton.ac.uk/classifiers/BlinkieGenreJ48DecisionTree.ser0.serial" => "Genre J48 Decision Tree",
	);
	if (array_key_exists($uri, $map))
		return $map[$uri];
	return $uri;
}

// query dbpedia for artists and their locations given a genre and optionally a 
// country URI
function dbpediaartists($genreuri, $countryuri = null) {
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
	$dbartists = sparqlquery(ENDPOINT_DBPEDIA, $query);
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
	$result = sparqlquery(ENDPOINT_GEONAMES, $query);

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
	$query = prefix("owl") . "
		SELECT * WHERE {
			?bbcuri owl:sameAs <" . $dbpediauri . "> .
			FILTER regex(str(?bbcuri), \"^http://www.bbc.co.uk/\") .
		}
	";
	$result = sparqlquery(ENDPOINT_BBC, $query);

	if (empty($result))
		return false;

	return $result[0]["bbcuri"];
}

// get info from the BBC, given a BBC URI
function bbcinfo($bbcuri) {
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
	$result = sparqlquery(ENDPOINT_BBC, $query);

	if (empty($result))
		return array();
	return $result[0];
}

// get all associations for given array of signals
function getassociations($signals) {
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
		$rows = sparqlquery(ENDPOINT_RESULTS, $query, "rows", 60); // cache for one minute -- results can update quickly
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

// check an email address
function isemail($email) { //from http://www.ilovejackdaniels.com/php/email-address-validation/
	// First, we check that there's one @ symbol, and that the lengths are right
	if(!preg_match("%^[^@]{1,64}@[^@]{1,255}$%", $email)) {
		// Email invalid because wrong number of characters in one section, or wrong number of @ symbols.
		return false;
	}
	// Split it into sections to make life easier
	$email_array = explode("@", $email);
	$local_array = explode(".", $email_array[0]);
	for($i = 0; $i < sizeof($local_array); $i++) {
		 if(!preg_match("%^(([A-Za-z0-9!#$\%&'*+/=?^_`{|}~-][A-Za-z0-9!#$\%&'*+/=?^_`{|}~\.-]{0,63})|(\"[^(\\|\")]{0,62}\"))$%", $local_array[$i])) {
			return false;
		}
	}
	if(!preg_match("%^\[?[0-9\.]+\]?$%", $email_array[1])) { // Check if domain is IP. If not, it should be valid domain name
		$domain_array = explode(".", $email_array[1]);
		if(sizeof($domain_array) < 2) {
			return false; // Not enough parts to domain
		}
		for($i = 0; $i < sizeof($domain_array); $i++) {
			if(!preg_match("%^(([A-Za-z0-9][A-Za-z0-9-]{0,61}[A-Za-z0-9])|([A-Za-z0-9]+))$%", $domain_array[$i])) {
				return false;
			}
		}
	}
	return true;
}

// get modified time of remote file
function remote_filemtime($url) {
	$fp = fopen($url, "r");
	if (!$fp)
		return false;
	$md = stream_get_meta_data($fp);
	foreach ($md["wrapper_data"] as $data) {
		// handle redirection
		if (substr(strtolower($data), 0, 10) == "location: ") {
			$newurl = substr($data, 10);
			fclose($fp);
			return remote_filemtime($newurl);
		}
		if (substr(strtolower($data), 0, 15) == "last-modified: ") {
			fclose($fp);
			return strtotime(substr($data, 15));
		}
	}
	return false;
}

// fetch collection RDF and parse its info
function getcollectioninfo($uri, &$errors) {
	require_once "include/arc/ARC2.php";
	$parser = ARC2::getRDFParser();
	$parser->parse($uri);
	if (!empty($parser->errors)) {
		$errors = $parser->errors;
		return false;
	}

	$collection = array();
	$collection["index"] = $parser->getSimpleIndex();
	$collection["modified"] = remote_filemtime($uri);
	$collection["hash"] = null;
	$collection["uri"] = $uri;
	$collection["groundings"] = null;

	return $collection;
}

function getcollections() {
	require_once "include/arc/ARC2.php";
	$uristem = "http://collections.nema.ecs.soton.ac.uk/";

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

	usort($collections, "sortcollectionbydate");
	$collections = array_reverse($collections);

	return $collections;
}

function sortcollectionbydate($a, $b) {
	return $a["modified"] - $b["modified"];
}

// flash -- store a success message to be displayed on the next page
function flash($message = "Success") {
	if (!isset($_SESSION["flash"]) || !is_array($_SESSION["flash"]))
		$_SESSION["flash"] = array();
	$_SESSION["flash"][] = $message;
}

// user functions -- administer myexperiment user info

function user_logout() {
	if (isset($_SESSION["cc_myexp_user"]))
		unset($_SESSION["cc_myexp_user"]);
	return true;
}

function user_login($username, $password, &$errors) {
	require_once SITEROOT_LOCAL . "include/arc/ARC2.php";
	require_once "Graphite.php";

	user_logout();

	// make a Reader object and set it to use our credentials with HTTP basic
	$reader = ARC2::getComponent("Reader", array("arc_reader_credentials" => array(MYEXPERIMENT_DOMAIN => $username . ":" . $password)));

	// make a Parser object and set it to use the Reader above
	$parser = ARC2::getRDFParser();
	$parser->setReader($reader);

	// fetch the myexperiment whoami RDF
	$parser->parse("http://" . MYEXPERIMENT_DOMAIN . "/whoami.rdf");

	// abort if there are errors
	$errors = $reader->getErrors();
	if (!empty($errors))
		return false;

	// put the triples in a Graphite graph
	$graph = new Graphite($GLOBALS["ns"]);
	$graph->addTriples($parser->getTriples());

	// store user info in session data
	$user = $graph->allOfType("mebase:User")->current();

	$_SESSION["cc_myexp_user"] = array(
		"uri" => (string) $user->uri,
		"homepage" => (string) $user->get("foaf:homepage"),
		"name" => (string) $user->get("sioc:name"),
		"avatar" => (string) $user->get("sioc:avatar"),
	);

	return true;
}

function user_loggedin() {
	return isset($_SESSION["cc_myexp_user"]);
}

function user_name() {
	if (!user_loggedin()) {
		trigger_error("Tried to get username of a user who was not logged in", E_USER_WARNING);
		return false;
	}
	return $_SESSION["cc_myexp_user"]["name"];
}

function user_uri() {
	if (!user_loggedin()) {
		trigger_error("Tried to get URI of a user who was not logged in", E_USER_WARNING);
		return false;
	}
	return $_SESSION["cc_myexp_user"]["uri"];
}

// return the name of a person represented by the given URI
function person_uri_to_name($uri) {
	static $person_uri_to_name = array();
	if (isset($person_uri_to_name[$uri]))
		return $person_uri_to_name[$uri];

	require_once SITEROOT_LOCAL . "include/arc/ARC2.php";
	require_once "Graphite.php";

	$graph = new Graphite($GLOBALS["ns"]);
	$graph->load($uri);
	$name = $graph->resource($uri)->get("foaf:name", "sioc:name");
	if (get_class($name) == "Graphite_Null")
		return false;

	$person_uri_to_name[$uri] = (string) $name;
	return (string) $name;
}

// return true if a string looks like a URI
function is_uri($string) {
	return (boolean) preg_match('%^https?$%', parse_url($string, PHP_URL_SCHEME));
}

// return HTML for a creator, which could be a URI or an arbitrary string
function prettycreator($creator) {
	if (is_uri($creator))
		return htmlspecialchars(person_uri_to_name($creator)) . "\n" . urilink($creator);
	return htmlspecialchars($creator);
}

?>
