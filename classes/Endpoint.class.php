<?php

class Endpoint {
	private $url = null;
	private $errors = array();
	private $capabilities = null;
	private $probequeries = null;

	private $name = null;
	private $description = null;

	public function __construct($url, $name, $description = null, $fresh = false) {
		// basic checks
		if (empty($url))
			$this->errors[] = "No endpoint URL given";
		else if (!preg_match('%^https?://%', $url))
			$this->errors[] = "Couldn't parse endpoint URL";
		if (empty($name))
			$this->errors[] = "No endpoint name given";

		if (!empty($this->errors))
			return;

		// set the properties
		$this->url = $url;
		$this->name($name);
		$this->description($description); // if null this will get the description -- no harm

		// load any existing data about the endpoint
		if (file_exists($this->serializedpath())) {
			$serializedendpoint = Endpoint::load($this->url());
			$this->capabilities = $serializedendpoint->capabilities();
			$this->probequeries = $serializedendpoint->probequeries();
			$this->errors = $serializedendpoint->errors();
			$this->name = $serializedendpoint->name();
			$this->description = $serializedendpoint->description();
			unset($serializedendpoint);
		}

		// clear the cache if required
		if ($fresh)
			$this->clearcache();

		// probe if needed
		if (!file_exists($this->serializedpath()) || $fresh)
			$this->probe();
	}

	// get the endpoint URL
	public function url() {
		return $this->url;
	}

	// get or set the endpoint name
	public function name($name = null) {
		if (is_null($name))
			return $this->name;
		if (empty($name))
			trigger_error("tried to give an endpoint an empty name", E_USER_ERROR);
		$this->name = $name;
	}

	// get or set the endpoint description
	public function description($description = null) {
		if (is_null($description))
			return $this->description;
		if (empty($description))
			$this->description = null;
		$this->description = $description;
	}

	// get the hash of the endpoint's URL (used for its cache directory)
	public function hash() {
		return md5($this->url());
	}

	// get the cache directory's full path
	public function cachedir() {
		return SITEROOT_LOCAL . "cache/" . $this->hash();
	}

	// get the serialized endpoint file's full path
	public function serializedpath() {
		return self::serializedpathbyid($this->url());
	}

	// clear this endpoint's query cache and forget what has been probed
	public function clearcache() {
		if (is_dir($this->cachedir()))
			rmrecursive($this->cachedir());
		$this->probequeries = null;
		$this->capabilities = null;
	}

	// query the endpoint
	// takes all arguments of sparqlquery() except the first one (endpoint)
	public function query() {
		return call_user_func_array("sparqlquery", array_merge(array($this->url()), func_get_args()));
	}

	// given an endpoint URL, probe it to find out what comparisons we can do on 
	// its music data
	public function probe() {
		$this->capabilities = array();
		$this->probequeries = array();

		// see if we can simply get back any triples at all. if a cache 
		// directory already exists for this endpoint we can skip this
		if (!is_dir($this->cachedir())) {
			// set up Arc
			require_once SITEROOT_LOCAL . "include/arc/ARC2.php";
			$config = array(
				"remote_store_endpoint" => $this->url,
				"reader_timeut" => 20,
				"ns" => $GLOBALS["ns"],
			);
			$store = ARC2::getRemoteStore($config);

			$result = $store->query("SELECT ?s ?p ?o WHERE { ?s ?p ?o . } LIMIT 1", "rows");

			if (count($store->getErrors())) {
				$this->errors[] = "Problem communicating with endpoint. Arc's errors follow.";
				$this->errors = array_merge($this->errors, $store->getErrors());
				return null;
			}

			if (empty($result)) {
				$this->errors[] = "No triples were returned -- verify the endpoint URL is correct";
				return null;
			}
		}

		// check that mo:MusicArtist, mo:Record, mo:Track and mo:Signal exist and 
		// are joined with foaf:maker, mo:track and mo:published_as
		$result = $this->query($this->probequeries[] = prefix(array("mo", "foaf")) . "
			SELECT * WHERE {
				?artist
					a mo:MusicArtist .
				?record
					a mo:Record ;
					foaf:maker ?artist ;
					mo:track ?track .
				?track
					a mo:Track .
				?signal
					a mo:Signal ;
					mo:published_as ?track .
			}
			LIMIT 1
		");
		if (!empty($result)) {
			$this->capabilities[] = new Capability("relationships", "Relationships between artists, records, tracks and signals are present", "There exist objects of types mo:MusicArtist, mo:Record, mo:Track and mo:Signal linked in such a way that their relationships can be understood.", array(
				sparqlresulttotriple("artist", "rdf:type", "mo:MusicArtist", $result[0]),
				sparqlresulttotriple("record", "rdf:type", "mo:Record", $result[0]),
				sparqlresulttotriple("record", "foaf:maker", "artist", $result[0]),
				sparqlresulttotriple("record", "mo:track", "track", $result[0]),
				sparqlresulttotriple("track", "rdf:type", "mo:Track", $result[0]),
				sparqlresulttotriple("signal", "rdf:type", "mo:Signal", $result[0]),
				sparqlresulttotriple("signal", "mo:published_as", "track", $result[0]),
			));
		}

		// artist name
		$result = $this->query($this->probequeries[] = prefix(array("mo", "foaf")) . "
			SELECT * WHERE {
				?artist
					a mo:MusicArtist ;
					foaf:name ?artistname .
			}
			LIMIT 1
		");
		if (!empty($result)) {
			$this->capabilities[] = new Capability("artistname", "Artist names are available", "Objects of type mo:MusicArtist have names available via the foaf:name predicate.", array(
				sparqlresulttotriple("artist", "rdf:type", "mo:MusicArtist", $result[0]),
				sparqlresulttotriple("artist", "foaf:name", "artistname", $result[0]),
			));
		}

		// record name
		$result = $this->query($this->probequeries[] = prefix(array("mo", "dc")) . "
			SELECT * WHERE {
				?record
					a mo:Record ;
					dc:title ?recordname .
			}
			LIMIT 1
		");
		if (!empty($result))
			$this->capabilities[] = new Capability("recordname", "Record names are available", "Objects of type mo:Record have names available via the dc:title predicate.", array(
				sparqlresulttotriple("record", "rdf:type", "mo:Record", $result[0]),
				sparqlresulttotriple("record", "dc:title", "recordname", $result[0]),
			));

		// track name
		$result = $this->query($this->probequeries[] = prefix(array("mo", "dc")) . "
			SELECT * WHERE {
				?track
					a mo:Track ;
					dc:title ?trackname .
			}
			LIMIT 1
		");
		if (!empty($result))
			$this->capabilities[] = new Capability("trackname", "Track names are available", "Objects of type mo:Track have names available via the dc:title predicate.", array(
				sparqlresulttotriple("track", "rdf:type", "mo:Track", $result[0]),
				sparqlresulttotriple("track", "dc:title", "trackname", $result[0]),
			));

		// artist country
		$result = $this->query($this->probequeries[] = prefix(array("mo", "foaf", "geo")) . "
			SELECT * WHERE {
				?artist
					a mo:MusicArtist ;
					foaf:based_near ?basednear .
				?basednear geo:inCountry ?country .
			}
			LIMIT 1
		");
		if (!empty($result))
			$this->capabilities[] = new Capability("artistcountry", "Artist country data available", "Artists are declared to be foaf:based_near a place and the triples which are necessary to determine which country this place is in are also present.", array(
				sparqlresulttotriple("artist", "rdf:type", "mo:MusicArtist", $result[0]),
				sparqlresulttotriple("artist", "foaf:based_near", "basednear", $result[0]),
				sparqlresulttotriple("basednear", "geo:inCountry", "country", $result[0]),
			));

		// date of some kind
		$result = $this->query($this->probequeries[] = prefix(array("mo", "dc")) . "
			SELECT * WHERE {
				{
					?record a mo:Record .
					{ ?record dc:date ?recorddate . } UNION { ?record dc:created ?recordcreated }
				} UNION {
					?track a mo:Track .
					{ ?track dc:date ?trackdate . } UNION { ?track dc:created ?trackcreated }
				}
			}
			LIMIT 1
		");
		if (!empty($result)) {
			$this->capabilities[] = new Capability("recorddate", "Date information available", "Either mo:Record or mo:Track objects have date information provided by either the dc:date or dc:created predicates.", isset($result[0]["track"]) ? array(
				sparqlresulttotriple("track", "rdf:type", "mo:Track", $result[0]),
				isset($result[0]["trackdate"]) ? sparqlresulttotriple("track", "dc:date", "trackdate", $result[0]) : sparqlresulttotriple("track", "dc:created", "trackcreated", $result[0]),
			) : array(
				sparqlresulttotriple("record", "rdf:type", "mo:Record", $result[0]),
				isset($result[0]["recorddate"]) ? sparqlresulttotriple("record", "dc:date", "recorddate", $result[0]) : sparqlresulttotriple("record", "dc:created", "recordcreated", $result[0]),
			));
		}

		// record tag
		$result = $this->query($this->probequeries[] = prefix(array("mo", "tags")) . "
			SELECT * WHERE {
				?record
					a mo:Record ;
					tags:taggedWithTag ?tag .
			}
			LIMIT 1
		");
		if (!empty($result))
			$this->capabilities[] = new Capability("recordtag", "Records are tagged", "Objects of type mo:Record are tagged using the tags:taggedWithTag predicate.", array(
				sparqlresulttotriple("record", "rdf:type", "mo:Record", $result[0]),
				sparqlresulttotriple("record", "tags:taggedWithTag", "tag", $result[0]),
			));

		// track number
		$result = $this->query($this->probequeries[] = prefix(array("mo")) . "
			SELECT * WHERE {
				?track
					a mo:Track ;
					mo:track_number ?tracknumber .
			}
			LIMIT 1
		");
		if (!empty($result))
			$this->capabilities[] = new Capability("tracknumber", "Track numbers available", "Objects of type mo:Track in this endpoint are linked via mo:track_number to their track numbers.", array(
				sparqlresulttotriple("track", "rdf:type", "mo:Track", $result[0]),
				sparqlresulttotriple("track", "mo:track_number", "tracknumber", $result[0]),
			));

		// avaliable_as (could be MP3, could be something else)
		// if we get an MP3 let's say we can ground against it too
		$result = $this->query($this->probequeries[] = prefix(array("mo")) . "
			SELECT * WHERE {
				?track
					a mo:Track ;
					mo:available_as ?manifestation .
			}
			LIMIT 1
		");
		if (!empty($result)) {
			$this->capabilities[] = new Capability("availableas", "Samples available", "The endpoint links mo:Track objects via mo:available_as statements to other resources. These could be anything but in practice tend to be playlist files, audio files or torrent files.", array(
				sparqlresulttotriple("track", "rdf:type", "mo:Track", $result[0]),
				sparqlresulttotriple("track", "mo:available_as", "manifestation", $result[0]),
			));

			// if manifestation gives an MP3 we can try to ground on this
			if (preg_match('%^https?://%', (string) $result[0]["manifestation"]) && ismp3((string) $result[0]["manifestation"]))
				$this->capabilities[] = new Capability("grounding", "Can be grounded against", "The endpoint can be grounded against: mo:Track objects are linked via mo:available_as statements to URLs which when resolved give MP3 files.", array());
		}

		// grounding (proper grounding)
		$result = $this->query($this->probequeries[] = prefix(array("mo")) . "
			SELECT * WHERE {
				?track
					a mo:Track ;
					mo:available_as ?manifestation .
				?manifestation
					a mo:AudioFile .
			}
			LIMIT 1
		");
		if (!empty($result))
			$this->capabilities[] = new Capability("grounding", "Can be grounded against", "The endpoint can be grounded against: mo:Track objects are linked via mo:available_as statements to mo:AudioFile objects.", array(
				sparqlresulttotriple("track", "rdf:type", "mo:Track", $result[0]),
				sparqlresulttotriple("track", "mo:available_as", "manifestation", $result[0]),
				sparqlresulttotriple("manifestation", "rdf:type", "mo:AudioFile", $result[0]),
			));

		return empty($this->errors);
	}

	// return array of this endpoint's capabilities
	public function capabilities() {
		return $this->capabilities;
	}

	// return an array of the Sparql queries which were used when probing the 
	// endpoint
	public function probequeries() {
		return $this->probequeries;
	}

	// return the current array of error messages
	public function errors() {
		return $this->errors;
	}

	// return true if this endpoint has the given capabilities (string 
	// capability ID or Capability object with the same ID, or array of any 
	// combination of these)
	public function hascapability($capabilities) {
		if (!is_array($capabilities))
			$capabilities = array($capabilities);

		$has = 0;
		foreach ($capabilities as $capability) {
			foreach ($this->capabilities() as $cap) {
				if (is_string($capability) && $cap->id() == $capability || is_object($capability) && get_class($capability) == "Capability" && $capability->id() == $cap->id()) {
					$has++;
					continue 2;
				}
			}
		}

		return $has == count($capabilities);
	}

	// save this endpoint as a serialized object
	public function save() {
		if (!is_dir(dirname($this->serializedpath())))
			if (!mkdir(dirname($this->serializedpath()))) {
				$this->errors[] = "endpoints directory couldn't be made";
				return false;
			}
		if (file_put_contents($this->serializedpath(), serialize($this)))
			return true;
		$errors[] = "Endpoint couldn't be saved";
		return false;
	}

	// delete this endpoint, return false on failure (if it didn't exist that's 
	// counted as success)
	public function delete() {
		if (file_exists($this->serializedpath()))
			return unlink($this->serializedpath());
		return true;
	}

	// return the serialization file's full path by URL
	public static function serializedpathbyid($url, $hash = false) {
		return SITEROOT_LOCAL . "endpoints/" . ($hash ? $url : md5($url));
	}

	// is an endpoint with the given URL already saved?
	public static function exists($url, $hash = false) {
		return file_exists(self::serializedpathbyid($url, $hash));
	}

	// load a serialized endpoint object by URL or hash
	// if given an array, return an array of many. all must be hashes or URLs
	public static function load($url, $hash = false) {
		if (is_array($url)) {
			$endpoints = array();
			foreach ($url as $u)
				$endpoints[] = self::load($u, $hash);
			return $endpoints;
		}

		if (!self::exists($url, $hash))
			trigger_error("tried to load a non-existant endpoint " . ($hash ? "by hash " : "") . "'$url'", E_USER_ERROR);
		return unserialize(file_get_contents(self::serializedpathbyid($url, $hash)));
	}

	// load all saved endpoints, return them as an array
	public static function all() {
		$endpoints = array();
		foreach (glob(SITEROOT_LOCAL . "endpoints/*") as $file) {
			$file = basename($file);
			if (!preg_match('%^[0-9a-f]{32}$%i', $file))
				continue;
			$endpoints[] = self::load($file, true);
		}
		return $endpoints;
	}

	// return all endpoints with a particular (array of) capability
	public static function allwith($capabilities) {
		$possible = self::all();
		$good = array();
		foreach ($possible as $ep)
			if ($ep->hascapability($capabilities))
				$good[] = $ep;
		return $good;
	}

	// return the common capabilities of all given endpoints
	public static function commoncapabilities($endpoints) {
		$capabilities = array();

		// collect all distinct capabilities (different IDs)
		foreach ($endpoints as $ep) {
			foreach ($ep->capabilities() as $epcap) {
				$exists = false;
				foreach ($capabilities as $cap) {
					if ($cap->id() == $epcap->id()) {
						$exists = true;
						break;
					}
				}
				if (!$exists)
					$capabilities[] = $epcap;
			}
		}

		// for each capability, loop through endpoints and remove it if an 
		// endpoint doesn't provide it
		foreach ($capabilities as $index => $cap) {
			foreach ($endpoints as $ep) {
				if (!$ep->hascapability($cap)) {
					unset($capabilities[$index]);
					continue 2;
				}
			}
		}

		return $capabilities;
	}
}

?>
