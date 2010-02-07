<?php

class Elixir_Session {
	static protected $_instance = null;
	
	protected $_objects = array();
	protected $_changes = array();
	protected $_index = array();
	protected $_status = array();
	
	public function __construct() {
	}
	
	static public function getDefaultInstance() {
		if(!static::$_instance) {
			static::$_instance = new static();
		}
		return static::$_instance;
	}
	
	public function setStatus($elixir, $details = null) {
		$hash = spl_object_hash($elixir);
		
		// if not in session, add.
		if(!isset($this->_objects[$hash])) {
			$this->_objects[$hash] = $elixir;
		}
		// if we can index id, do so
		if($id = $elixir->getId()) {
			$this->_index[$id] = $hash;
		}
		// index the change that was made.
		if($details) {
			$class = get_class($elixir);
			if(!isset($this->_changes[$class])) {
				$this->_changes[$class] = array();
			}
			foreach((array)$details as $field) {
				if(!isset($this->_changes[$class][$field])) {
					$this->_changes[$class][$field] = array();
				}
				$this->_changes[$class][$field][$hash] = true;
			}
		}
		// index status
		$_status[$hash] = $elixir->getStatus();
	}
	
	public function get($id) {
		return isset($this->_index[$id]) ? $this->_objects[$this->_index[$id]] : false;
	}
}
