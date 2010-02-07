<?php
class Elixir_Array implements countable {
	protected $_type = null;
	protected $_session = null;
	protected $_query = null;
	protected $_values = null;
	protected $_options = array(
		'lazy' => true
	);
	
	protected $_pointer = 0;
	
	public function __construct($query, $session, $options = array()) {
		$this->_query = $query;
		$this->_session = $session;
		$this->_options = array_merge($this->_options, $options);
	}
	
	public function __get($name) {
		//TODO
		return null;
	}
	
	public function __set($name, $value) {
		// TODO
		return;
	}
	
	public function setType($type) {
		if(!is_subclass_of($type, 'Elixir_Object')) {
			throw new Elixir_Exception('invalid type');
		}
		$this->_type = $type;
	}
	
	public function getType() {
		return $this->_type;
	}
	
	public function getOption($option) {
		try {
			return $this->_options[$options];
		}
		catch(Exception $e) {
			throw new Elixir_Exception('no such option');
		}
	}
	
	public function setOption($option, $value) {
		$this->_options[strtolower($option)] = $value;
		return $this;
	}
	
	public function refresh() {
		if(!$this->_type) {
			throw new Elixir_Exception('no type set');
		}
		
		// get the db rows.
		$this->_values = $this->_query->fetchAssoc();
		
		// reset internal array pointer
		$this->rewind();
	} 

	/**
	 * Get an object from a db row.
	 * 
	 * @param array $row
	 * @return Elixir_Object
	 */
	protected function _load($row) {
		return call_user_func(array($this->_type, 'getByDbRow'), $row, $this->_session, !$this->_options['lazy']);
	}
	
	//
	// *** Implement Countable ***
	//
	
	/**
	 * Count elements in array
	 * @return int
	 */
	public function count() {
		if($this->_values === null) {
			$this->refresh();
		}
		return count($this->_values);
	}
	
	//
	// *** Implement Iterator ***
	//
	
	public function key() {
		return $this->valid() ? $this->_pointer : null;
	}
	
	public function current() {
		try {
			return $this->offsetGet($this->_pointer);
		}
		catch(OutOfBoundsException $e) {
			return false;
		}
	}
	
	public function next() {
		++$this->_pointer;
		try {
			return $this->current();
		}
		catch(Elixir_Exception_NoSuchRow $e) {
			if(!$this->_options['lazy']) {
				return $this->next();
			}
			throw $e;
		}
	}
	
	public function rewind() {
		$this->_pointer = 0;
		return;
	}
	
	public function valid() {
		return $this->offsetExists($this->_pointer);
	}
	
	//
	// *** Implements ArrayAccess ***
	//
	
	public function offsetExist($offset) {
		if($this->_values === null) {
			$this->refresh();
		}
		return isset($_values[(int)$offset]);
	}
	
	public function offsetGet($offset) {
		if(!$this->offsetExist($offset)) {
			throw new OutOfBoundsException();
		}
		return $this->_load($this->_values[(int)$offset]);
	}
	
	public function offsetSet($offset, $value) {
		//TODO
		return;
	}
	
	public function offsetUnset($offset) {
		//TODO
		return;
	}
}