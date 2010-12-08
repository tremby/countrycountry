<?php

class Capability {
	private $id;
	private $name;
	private $description;
	private $triples;

	public function __construct($id, $name, $description, $triples = array()) {
		$this->id = $id;
		$this->name = $name;
		$this->description = $description;
		$this->triples = $triples;
	}

	public function id() {
		return $this->id;
	}

	public function name() {
		return $this->name;
	}

	public function description() {
		return $this->description;
	}

	public function triples() {
		return $this->triples;
	}

	public function __toString() {
		return $this->name();
	}
}

?>
