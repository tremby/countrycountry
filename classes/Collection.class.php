<?php

class Collection {
	private $id = null;
	private $title = null;
	private $description = null;
	private $author = null;
	private $filters = array();
	public $data = array();
	private $query = null;
	private $results = array();
	private $groundhash = null;
	private $groundendpoint = null;
	private $groundedresults = array();

	public function __construct() {
		$this->id = md5(microtime());
	}

	// get collection's ID
	public function id() {
		return $this->id;
	}

	// get or set collection's title
	public function title($title = null) {
		if (!is_null($title)) {
			$this->title = $title;
			return $this;
		}
		return $this->title;
	}

	// get or set collection's description
	public function description($description = null) {
		if (!is_null($description)) {
			$this->description = $description;
			return $this;
		}
		return $this->description;
	}

	// get or set collection's author
	public function author($author = null) {
		if (!is_null($author)) {
			$this->author = $author;
			return $this;
		}
		return $this->author;
	}

	// return all filters which don't mention particular variables
	// if passed a filterlist, they're removed from that list
	public function filters($forbidden = array(), &$filterlist = null) {
		$ret = array();
		if (isset($filterlist))
			$filters = $filterlist;
		else
			$filters = $this->filters;
		foreach ($filters as $index => $filter) {
			foreach ($forbidden as $forbiddenvar)
				if (preg_match('%\?' . $forbiddenvar . '\b%', $filter))
					continue 2;
			if (isset($filterlist))
				unset($filterlist[$index]);
			$ret[] = $filter;
		}
		return $ret;
	}

	// empty the array of filters
	public function clearfilters() {
		$this->filters = array();
		return $this;
	}

	// add a filter
	public function addfilter($filter) {
		$this->filters[] = $filter;
		return $this;
	}

	// store the collection in session memory
	public function sessionStore() {
		if (!isset($_SESSION["collections"]) || !is_array($_SESSION["collections"]))
			$_SESSION["collections"] = array();
		$_SESSION["collections"][$this->id()] = $this;
	}

	// remove the collection from session memory
	public function sessionRemove() {
		if (!isset($_SESSION["collections"]) || !isset($_SESSION["collections"][$this->id()]))
			return;
		unset($_SESSION["collections"][$this->id()]);
	}

	// set and perform or retrieve the query
	public function query($query = null) {
		if (is_null($query))
			return $this->query;

		$this->query = $query;
		$this->results = queryjamendo($query);
	}

	// get results
	public function results() {
		return $this->results;
	}

	// get grounded results
	public function groundedresults() {
		return $this->groundedresults;
	}

	// get URI for this collection (may not have been minted yet)
	public function uri() {
		return "http://collections.nema.ecs.soton.ac.uk/signalcollection/" . $this->id();
	}

	// get ground hash which was generated last time the collection was grounded
	public function groundhash() {
		return $this->groundhash;
	}

	// get URI for this collection, grounded (may not have been minted yet)
	public function groundeduri() {
		return "http://collections.nema.ecs.soton.ac.uk/filecollection/" . $this->id() . "/" . $this->groundhash();
	}

	// ground the collection (find actual files for the signals) given a signal 
	// repository endpoint
	public function ground($endpoint) {
		global $ns;

		$this->groundhash = md5(microtime());
		$this->groundendpoint = $endpoint;

		require_once SITEROOT_LOCAL . "include/arc/ARC2.php";
		$conf = array(
			"ns" => $ns,
			"remote_store_endpoint" => $endpoint,
		);
		$store = ARC2::getRemoteStore($conf);

		$this->groundedresults = array();
		foreach ($this->results as $result) {
			$result = $store->query("
				" . prefix(array("rdf", "mo")) . "
				SELECT ?audiofile WHERE {
					<" . $result["track"] . "> mo:available_as ?audiofile .
					?audiofile a mo:AudioFile .
				}
			", "row");
			if (!empty($result))
				$this->groundedresults[] = $result["audiofile"];
		}
	}

	// get RDF
	public function rdf() {
		global $ns;
		require_once SITEROOT_LOCAL . "include/arc/ARC2.php";

		$conf = array("ns" => $ns);
		$triples = array(
			// resource map triples
			array(
				"s" => $this->uri(),
				"p" => $ns["rdf"] . "type",
				"o" => $ns["ore"] . "ResourceMap",
				"s_type" => "uri",
				"o_type" => "uri",
			),
			array(
				"s" => $this->uri(),
				"p" => $ns["ore"] . "describes",
				"o" => $this->uri() . "#aggregate",
				"s_type" => "uri",
				"o_type" => "uri",
			),
			array(
				"s" => $this->uri(),
				"p" => $ns["dc"] . "creator",
				"o" => "http://" . $_SERVER["HTTP_HOST"] . dirname($_SERVER["REQUEST_URI"]),
				"s_type" => "uri",
				"o_type" => "uri",
			),

			// aggregate triples
			array(
				"s" => $this->uri() . "#aggregate",
				"p" => $ns["rdf"] . "type",
				"o" => $ns["ore"] . "Aggregate",
				"s_type" => "uri",
				"o_type" => "uri",
			),
			array(
				"s" => $this->uri() . "#aggregate",
				"p" => $ns["rdf"] . "type",
				"o" => $ns["pv"] . "DataItem",
				"s_type" => "uri",
				"o_type" => "uri",
			),
			array(
				"s" => $this->uri() . "#aggregate",
				"p" => $ns["dc"] . "title",
				"o" => $this->title(),
				"s_type" => "uri",
				"o_type" => "literal",
			),
			array(
				"s" => $this->uri() . "#aggregate",
				"p" => $ns["dc"] . "creator",
				"o" => $this->author(),
				"s_type" => "uri",
				"o_type" => strpos($this->author(), "http://") === 0 ? "uri" : "literal",
			),
			array(
				"s" => $this->uri() . "#aggregate",
				"p" => $ns["pv"] . "createdBy",
				"o" => $this->uri() . "#execution",
				"s_type" => "uri",
				"o_type" => "uri",
			),

			// execution triples
			array(
				"s" => $this->uri() . "#execution",
				"p" => $ns["rdf"] . "type",
				"o" => $ns["pv"] . "QueryExecution",
				"s_type" => "uri",
				"o_type" => "uri",
			),
			array(
				"s" => $this->uri() . "#execution",
				"p" => $ns["pv"] . "usedGuideline",
				"o" => $this->query(),
				"s_type" => "uri",
				"o_type" => "literal",
			),
			array(
				"s" => $this->uri() . "#execution",
				"p" => $ns["pv"] . "performedBy",
				"o" => "http://" . $_SERVER["HTTP_HOST"] . dirname($_SERVER["REQUEST_URI"]),
				"s_type" => "uri",
				"o_type" => "uri",
			),

			// sparql query triples
			array(
				"s" => $this->uri() . "#sparql",
				"p" => $ns["rdf"] . "type",
				"o" => $ns["pv"] . "SPARQLQuery",
				"s_type" => "uri",
				"o_type" => "uri",
			),
		);

		// conditional triples
		if ($this->description()) $triples[] = array(
			"s" => $this->uri() . "#aggregate",
			"p" => $ns["dc"] . "description",
			"o" => $this->description(),
			"s_type" => "uri",
			"o_type" => "literal",
		);

		// aggregates signal triples
		foreach ($this->results() as $result) $triples[] = array(
			"s" => $this->uri() . "#aggregate",
			"p" => $ns["ore"] . "aggregates",
			"o" => $result["signal"],
			"s_type" => "uri",
			"o_type" => "uri",
		);

		// serialize to RDF+XML
		$ser = ARC2::getRDFXMLSerializer($conf);
		return $ser->getSerializedTriples($triples);
	}

	// get grounded RDF
	public function groundedrdf() {
		global $ns;
		require_once SITEROOT_LOCAL . "include/arc/ARC2.php";

		$conf = array("ns" => $ns);
		$triples = array(
			// resource map triples
			array(
				"s" => $this->groundeduri(),
				"p" => $ns["rdf"] . "type",
				"o" => $ns["ore"] . "ResourceMap",
				"s_type" => "uri",
				"o_type" => "uri",
			),
			array(
				"s" => $this->groundeduri(),
				"p" => $ns["ore"] . "describes",
				"o" => $this->groundeduri() . "#aggregate",
				"s_type" => "uri",
				"o_type" => "uri",
			),
			array(
				"s" => $this->groundeduri(),
				"p" => $ns["dc"] . "creator",
				"o" => "http://" . $_SERVER["HTTP_HOST"] . dirname($_SERVER["REQUEST_URI"]),
				"s_type" => "uri",
				"o_type" => "uri",
			),

			// aggregate triples
			array(
				"s" => $this->groundeduri() . "#aggregate",
				"p" => $ns["rdf"] . "type",
				"o" => $ns["ore"] . "Aggregate",
				"s_type" => "uri",
				"o_type" => "uri",
			),
			array(
				"s" => $this->groundeduri() . "#aggregate",
				"p" => $ns["rdf"] . "type",
				"o" => $ns["pv"] . "DataItem",
				"s_type" => "uri",
				"o_type" => "uri",
			),
			array(
				"s" => $this->groundeduri() . "#aggregate",
				"p" => $ns["pv"] . "createdBy",
				"o" => $this->groundeduri() . "#grounding",
				"s_type" => "uri",
				"o_type" => "uri",
			),

			// grounding triples
			array(
				"s" => $this->groundeduri() . "#grounding",
				"p" => $ns["rdf"] . "type",
				"o" => $ns["pv"] . "DataAccess",
				"s_type" => "uri",
				"o_type" => "uri",
			),
			array(
				"s" => $this->groundeduri() . "#grounding",
				"p" => $ns["pv"] . "accessedService",
				"o" => "http://repository.nema.ecs.soton.ac.uk/",
				"s_type" => "uri",
				"o_type" => "uri",
			),
			array(
				"s" => $this->groundeduri() . "#grounding",
				"p" => $ns["pv"] . "performedBy",
				"o" => "http://" . $_SERVER["HTTP_HOST"] . dirname($_SERVER["REQUEST_URI"]),
				"s_type" => "uri",
				"o_type" => "uri",
			),
			array(
				"s" => $this->groundeduri() . "#grounding",
				"p" => $ns["pv"] . "usedData",
				"o" => $this->uri() . "#aggregate",
				"s_type" => "uri",
				"o_type" => "uri",
			),
		);

		// aggregates file triples
		foreach ($this->groundedresults() as $result) $triples[] = array(
			"s" => $this->groundeduri() . "#aggregate",
			"p" => $ns["ore"] . "aggregates",
			"o" => $result,
			"s_type" => "uri",
			"o_type" => "uri",
		);

		// serialize to RDF+XML
		$ser = ARC2::getRDFXMLSerializer($conf);
		return $ser->getSerializedTriples($triples);
	}

	// get a collection object from session memory given its ID
	public static function fromID($id) {
		if (!isset($_SESSION["collections"]))
			return false;
		if (!isset($_SESSION["collections"][$id]))
			return false;
		return $_SESSION["collections"][$id];
	}
}

?>
