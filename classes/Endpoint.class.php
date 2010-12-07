<?php

class Endpoint {
	private $url = null;
	private $capabilitytriples = array();
	private $errors = array();
	private $probequeries = array();

	public function __construct($url) {
		// basic checks
		if (empty($url)) {
			$this->errors[] = "No endpoint URL given";
			return;
		}
		if (!preg_match('%^https?://%', $url)) {
			$this->errors[] = "Couldn't parse endpoint URL";
			return;
		}

		// set the endpoint URL and probe for capabilities
		$this->url = $url;
		$this->probe();
	}

	// get the endpoint URL
	public function url() {
		return $this->url;
	}

	// get the hash of the endpoint's URL (used for its cache directory)
	public function hash() {
		return md5($this->url());
	}

	// get the cache directory's full path
	public function cachedir() {
		return SITEROOT_LOCAL . "cache/" . $this->hash();
	}

	// clear this endpoint's query cache
	public function clearcache() {
		if (is_dir($this->cachedir()))
			rmrecursive($this->cachedir());
	}

	// query the endpoint
	// takes all arguments of sparqlquery() except the first one (endpoint)
	public function query() {
		return call_user_func_array("sparqlquery", array_merge(array($this->url()), func_get_args()));
	}

	// given an endpoint URL, probe it to find out what comparisons we can do on 
	// its music data
	private function probe() {
		$this->capabilitytriples = array();

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
			$this->capabilitytriples["relationships"] = array(
				sparqlresulttotriple("artist", "rdf:type", "mo:MusicArtist", $result[0]),
				sparqlresulttotriple("record", "rdf:type", "mo:Record", $result[0]),
				sparqlresulttotriple("record", "foaf:maker", "artist", $result[0]),
				sparqlresulttotriple("record", "mo:track", "track", $result[0]),
				sparqlresulttotriple("track", "rdf:type", "mo:Track", $result[0]),
				sparqlresulttotriple("signal", "rdf:type", "mo:Signal", $result[0]),
				sparqlresulttotriple("signal", "mo:published_as", "track", $result[0]),
			);
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
			$this->capabilitytriples["artistname"] = array(
				sparqlresulttotriple("artist", "rdf:type", "mo:MusicArtist", $result[0]),
				sparqlresulttotriple("artist", "foaf:name", "artistname", $result[0]),
			);
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
			$this->capabilitytriples["recordname"] = array(
				sparqlresulttotriple("record", "rdf:type", "mo:Record", $result[0]),
				sparqlresulttotriple("record", "dc:title", "recordname", $result[0]),
			);

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
			$this->capabilitytriples["trackname"] = array(
				sparqlresulttotriple("track", "rdf:type", "mo:Track", $result[0]),
				sparqlresulttotriple("track", "dc:title", "trackname", $result[0]),
			);

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
			$this->capabilitytriples["artistcountry"] = array(
				sparqlresulttotriple("artist", "rdf:type", "mo:MusicArtist", $result[0]),
				sparqlresulttotriple("artist", "foaf:based_near", "basednear", $result[0]),
				sparqlresulttotriple("basednear", "geo:inCountry", "country", $result[0]),
			);

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
			if (isset($result[0]["track"]))
				$this->capabilitytriples["recorddate"] = array(
					sparqlresulttotriple("track", "rdf:type", "mo:Track", $result[0]),
					isset($result[0]["trackdate"]) ? sparqlresulttotriple("track", "dc:date", "trackdate", $result[0]) : sparqlresulttotriple("track", "dc:created", "trackcreated", $result[0]),
				);
			else
				$this->capabilitytriples["recorddate"] = array(
					sparqlresulttotriple("record", "rdf:type", "mo:Record", $result[0]),
					isset($result[0]["recorddate"]) ? sparqlresulttotriple("record", "dc:date", "recorddate", $result[0]) : sparqlresulttotriple("record", "dc:created", "recordcreated", $result[0]),
				);
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
			$this->capabilitytriples["recordtag"] = array(
				sparqlresulttotriple("record", "rdf:type", "mo:Record", $result[0]),
				sparqlresulttotriple("record", "tags:taggedWithTag", "tag", $result[0]),
			);

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
			$this->capabilitytriples["tracknumber"] = array(
				sparqlresulttotriple("track", "rdf:type", "mo:Track", $result[0]),
				sparqlresulttotriple("track", "mo:track_number", "tracknumber", $result[0]),
			);

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
			$this->capabilitytriples["availableas"] = array(
				sparqlresulttotriple("track", "rdf:type", "mo:Track", $result[0]),
				sparqlresulttotriple("track", "mo:available_as", "manifestation", $result[0]),
			);
			if (preg_match('%^https?://%', (string) $result[0]["manifestation"]) && ismp3((string) $result[0]["manifestation"]))
				$this->capabilitytriples["grounding"] = array(); // manifestation gives an MP3 -- we can try to ground on this
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
			$this->capabilitytriples["grounding"] = array(
				sparqlresulttotriple("track", "rdf:type", "mo:Track", $result[0]),
				sparqlresulttotriple("track", "mo:available_as", "manifestation", $result[0]),
				sparqlresulttotriple("manifestation", "rdf:type", "mo:AudioFile", $result[0]),
			);

		return !empty($this->errors);
	}

	// return an associative array of this endpoint's capabilities to the 
	// example triples which support it
	public function capabilitytriples() {
		return $this->capabilitytriples;
	}

	// return an array of this endpoint's capabilities
	public function capabilities() {
		return array_keys($this->capabilitytriples());
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
}

?>
