<?php
class Elixir_Array implements Countable, Iterator {
	protected $_type = null;
	protected $_session = null;
	protected $_constraint = null;
	protected $_fields = null;
	
	protected $_values = null;
	
	protected $_options = array(
		'lazy' => true
	);
	
	protected $_pointer = 0;
	
	public function __construct($session, $options = array()) {
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
	
	public function getType() {
		if(!$this->_type) {
			require_once 'Elixir/Exception/InvalidType.php';
			throw new Elixir_Exception_InvalidType('type not set');
		}
		return $this->_type;
	}

	public function setType($type) {
		if(!is_subclass_of($type, 'Elixir_Type')) {
			require_once 'Elixir/Exception/InvalidType.php';
			throw new Elixir_Exception_InvalidType();
		}
		$this->_type = $type;
	}
	
	public function getConstraint() {
		if(!$this->_constraint) {
			$this->_constraint = new Elixir_Constraint();
		}
		return $this->_constraint;
	}
	
	public function setConstraint(Elixir_Constraint $constraint) {
		$this->_constraint = $constraint;
		return $this;
	}
	
	public function getFields() {
		if(!$this->_fields) {
			$this->_fields = ${$this->getType()}::getGroupFields('default');
		}
		return $this->_fields;
	}
	
	public function setFields($fields) {
		$this->_fields = (array)$fields;
		return $this;
	}
	
	public function addFields($fields) {
		$this->_fields = array_unique($this->_fields + $fields);
		return $this;
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
		$type = $this->getType();
		$constraint = $this->getConstraint();
		$fields = $this->getFields();
		
		// get the db rows.
		$adapter = $type::getAdapter();
		$this->_values = $adapter->select($type, $fields, $constraint);
		
		// reset internal array pointer
		$this->rewind();
	} 

	public function getId() {
		
	}
	
	/**
	 * Get an object from a db row.
	 * 
	 * @param array $row
	 * @return Elixir_Type
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
		// if we have not been loaded yet, do a db count
		if($this->_values === null) {
			$type = $this->getType();
			$constraint = $this->getConstraint();
		
			// get the db rows.
			$adapter = $type::getAdapter();
			return $adapter->count($type, $constraint);
		}
		// if a select has already been perform, just count the results
		else {
			return count($this->_values);
		}
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
		return $this->offsetExist($this->_pointer);
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