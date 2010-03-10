<?php
/**
 * Represents a collection of constraints to be used when querying db.
 * 
 * @author Arwyn Hainsworth
 *
 */
class Elixir_Constraint {
	const OP_AND = 'and';
	const OP_OR = 'or';
	
	protected $_type;
	protected $_op;
	
	protected $_constraints_field  = array();
	protected $_constraints = array();
	
	public function __construct($type, $op = self::OP_AND) {
		if(!class_exists($type)) {
			require_once 'Elixir/Exception/Constraint/InvalidType.php';
			throw new Elixir_Exception_Constraint_InvalidType();
		}
		if(!in_array($op, array(self::OP_AND, self::OP_OR))) {
			require_once 'Elixir/Exception/Constraint/InvalidOp.php';
			throw new Elixir_Exception_Constraint_InvalidOp();
		}
		$this->_type = $type;
		$this->_op = $op;
	}

	public function clear() {
		return $this->clearFields()->clearConstraints();
	}
		
	public function clearFields() {
		$this->_constraints_field = array();
		$this->_id = null;
		return $this;
	}
		
	public function addStatic($condition) {
		$this->_constraints_static[] = $condition;
	}
	
	public function addField($field, $value) {
		$this->_constraints_field[] = compact('field', 'value');
		$this->_id = null;
		return $this;
	} 
	
	public function getFields() {
		return $this->_constraints_field;
	}
	
	public function clearConstraints() {
		$this->_constraints = array();
		$this->_id = null;
		return $this;
	}
	
	public function addConstraint(Elixir_Constraint $constraint) {
		if(!$this->getType() == $constraint->getType()) {
			require_once 'Elixir/Exception/Constraint/InvalidType.php';
			throw new Elixir_Exception_Constraint_InvalidType();
		}
		$this->_constraints[] = $constraint->fix();
		$this->_id = null;
		return $this;
	}
	
	public function getConstraints() {
		return $this->_constraints;
	}
	
	public function getType() {
		return $this->_type;
	}
	
	public function getOp() {
		return $this->_op;
	}
}