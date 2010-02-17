<?php
require_once 'Elixir/Db/Query/Interface.php';
require_once 'Elixir/Db/Adapter/Interface.php';

/**
 * Represents a Db Query where the the constraints are Elixir_Objects
 * 
 * @author Arwyn Hainsworth
 *
 */
class Elixir_Db_Query implements Elixir_Db_Query_Interface {
	protected $_adapter = null;
	protected $_class = null;
	
	protected $_select = array();
	protected $_constraint = array();
	
	/**
	 * Represents a Db Query where the the constraints are Elixir_Objects
	 * 
	 * @param Elixir_Db_Adapter_Interface $adapter
	 * @param string $class The Elixir_Object whos fields we need to select/update/delete
	 * @return Elixir_Db_Query
	 */
	public function _construct(Elixir_Db_Adapter_Interface $adapter, $class) {
		$this->_adapter = $adapter;
		if(!class_exists($class) || !is_subclass_of($class, 'Elixir_Object')) { 
			require_once 'Elixir/Exception/InvalidClass.php';
			throw new Elixir_Exception_InvalidClass();
		}
		$this->_class = $class;
	}
	
	/**
	 * Get the definition of the Elixir_Object we are targeting
	 * 
	 * @see Elixir_Object::getDefinition()
	 * @return array
	 */
	public function getDefinition() {
		return call_user_func(array($this->_class, 'getDefinition'));
	}
	
	/**
	 * Get Select Fields
	 * 
	 * @return array
	 */
	public function getSelectFields() {
		$this->_select = array_filter($this->_select);
		return array_keys($this->_select);
	}
	
	/**
	 * Add Select Field
	 * 
	 * @param string $field
	 * @return Elixir_Db_Query
	 */
	public function addSelectField($field) {
		$this->_select[$field] = true;
		return $this;	
	}
	
	/**
	 * Set Select Fields
	 * 
	 * @param array $fields [(string)field, ..]
	 * @return Elixir_Db_Query
	 */
	public function setSelectFields($fields) {
		$this->clearSelectFields();
		foreach($fields as $field) {
			$this->addSelectField();
		}
		return $this;
	}
	
	/**
	 * Clear Select Fields
	 * 
	 * @return Elixir_Db_Query
	 */
	public function clearSelectFields() {
		$this->_select = array();
		return $this;
	}
	
	/**
	 * Remove Select Fields
	 * 
	 * @param string $field
	 * @return Elixir_Db_Query
	 */
	public function removeSelectField($field) {
		$this->_select[$field] = false;
		return $this;
	}
	
	/**
	 * Add a Contraint
	 * 
	 * @param string $field
	 * @param mixed $value
	 * @param array $options
	 * @return Elixir_Db_Query
	 */
	public function addConstraint($field, $value, $options = array()) {
		$this->_constraint[] = compact('field', 'value', 'options');
		return $this;
	}
	
	/**
	 * Clear Constraints
	 * 
	 * @return Elixir_Db_Query
	 */
	public function clearConstraints() {
		$this->_constraint = array();
		return $this;
	}
	
	/**
	 * Build a select statement from given constraints
	 * 
	 * @param array $fields [(string)field, ..]
	 * @return string
	 */
	public function select($fields = null) {
		if(!$fields) {
			$fields = $this->getSelectFields();
		}
		return implode(' ', array($this->_buildSelect($fields), $this->_buildFrom($fields), $this->_buildWhere()));
	}
	
	/**
	 * Build update statement
	 * 
	 * @param array $data {(string)field: (mixed)value, ..}
	 * @return string
	 */
	public function update($data) {
		// TODO implement update
		return '';
	}

	/**
	 * Build SQL Delete statement
	 * 
	 * @return string
	 */
	public function delete() {
		return 'DELETE ' . $this->_buildFrom() . ' ' . $this->_buildWhere(); 
	}
	
	protected function _buildSelect($fields) {}
	
	protected function _buildUpdate($data) {}
	
	protected function _buildWhere() {}
	
	protected function _buildFrom($fields) {}		
}