<?php
/**
 * Represents a collection of constraints to be used when querying db.
 * 
 * @author Arwyn Hainsworth
 *
 */
class Elixir_Db_Constraint {
//	protected $_constraints_col    = array();
	protected $_constraints_field  = array();
//	protected $_constraints_static = array();
	
	public function __construct() {
	}

	public function clear() {
//		return $this->clearStatic()->clearCols()->clearFields();
		return $this->clearFields();
	}
	
//	public function clearStatic() {
//		$this->_constraints_static = array();
//		return $this;
//	}
	
	public function clearFields() {
		$this->_constraints_field = array();
		return $this;
	}
	
//	public function clearCols() {
//		$this->_constraints_col = array();
//		return $this;
//	}
	
	public function addStatic($condition) {
		$this->_constraints_static[] = $condition;
	}
	
//	public function addCol($column, $value) {
//		$this->_constraints_col[$column] = $value; 
//	}
	
	public function addField($field, $value) {
		$this->_constraints_field[] = compact('field', 'value');
		return $this;
	} 
	
	public function getFields() {
		return $this->_constraints_field;
	}
}