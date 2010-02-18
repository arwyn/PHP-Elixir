<?php
require_once 'Elixir/Db/Adapter/Interface.php';
require_once 'Elixir/Db/Query/Interface.php';
require_once 'Elixir/Db/Query.php';

abstract class Elixir_Db_Adapter implements Elixir_Db_Adapter_Interface {
	protected $_params = null;
	
	public function __construct($params) {
		$this->_params = $params;
	}
	
	static function factory($params) {
		// TODO
	}
	
	abstract public function init();
	
	public function select($class, $fields = null, Elixir_Db_Constraint $constraints = null, $order = null, $limit = null, $offset = null) {
		//get object definition
		$def = $this->_getDefinition();
		$table = $this->_getTable($class);
		
		$select = $this->_buildSelect($def, $fields);
		$from = $this->_buildFrom($def, $table, $constraints);
		$where = $this->_buildWhere($def, $constraints);
		$order = $order ? $this->_buildOrder($def, $order) : '';
		$limit = $limit ? $this->_buildLimit($def, $limit, $offset) : '';
		
		$query = implode(' ', array($select, $from, $where, $order, $limit));

		return $this->execSelect($query);
	}
	
	public function update($class, $data, Elixir_Db_Constraint $constraints = null) {
		$def = $this->_getDefinition($class);
		$table = $this->_getTable($class);
		
		$query = implode(' ', array(
			$this->_buildUpdate($def, $table, $constraints),
			$this->_buildSet($def, $data),
			$this->_buildWhere($def, $constraints)
		));
		
		return $this->execUpdate($query);
	} 
	
	public function insert($class, $data) {
		$def = $this->_getDefinition($class);
		$table = $this->_getTable($class);
		
		$query = implode(' ', array(
			$this->_buildInsert($table),
			$this->_buildValues($def, $data)
		));
		return $this->execInsert($query);
	}
	
	public function delete($class, Elixir_Db_Constraints $constraints = null) {
		$def = $this->_getDefinition($class);
		$table = $this->_getTable($class);
		
		$query = implode(' ', array(
			$this->_buildDelete($def, $table, $constraints),
			$this->_buildWhere($def, $constraints)
		));
		return $this->execDelete($query);
	}

	protected function _getDefinition($class) {
		if(!subclass_of($class, 'Elixir_Object')) {
			require_once 'Elixir/Exception/InvalidClass.php';
			throw new Elixir_Exception_InvalidClass();
		}
		return call_user_func(array($class, 'getDefinition'));
	}
	
	protected function _getTable($class) {
		if(!subclass_of($class, 'Elixir_Object')) {
			require_once 'Elixir/Exception/InvalidClass.php';
			throw new Elixir_Exception_InvalidClass();
		}
		return call_user_func(array($class, 'getTable'));
	}

	protected function _buildSelect($def, $fields) {
		$select = 'SELECT ';

		if($fields) {
			$cols = $this->_getColNamesFromFields($def, $fields);
			foreach($cols as $k => &$col) {
				$col = "d.$col";
			}
			$select .= implode(', ', $cols);
		}
		else {
			$select .= '*';
		}
		return $select;
	}
	
	protected function _buildFrom($def, $table, $constaints) {
		$from = "FROM $table AS d";
		
		// TODO: add JOINs if the constraints require them.
		return $from;
	}
	
	protected function _buildWhere($def, $constraints) {
		$where = 'WHERE ';
		
		$conds = array();
		foreach($constraints->getFields() as $constraint) {
			extract($constraint); // field, value
			if(!isset($def[$field])) {
				require_once 'Elixir/Exception/Constraint/InvalidField.php';
				throw new Elixir_Exception_Constraint_InvalidField();
			}
			switch(true) {
				// elixir object
				case is_object($value) && $value instanceof Elixir_Object:
					if($def[$field]['type'] != get_class($value)) {
						require_once 'Elixir/Exception/Constraint/InvalidClass.php';
						throw new Elixir_Exception_Constraint_InvalidClass();
					}
					$fields = $this->_convertObjectToFields($value);
					$cols = $this->_convertFieldsToCols($def[$field]['field'], $fields);
				
					$conds[] = '('.$this->_convertColsToCond($cols).')';
					break;
				// elixir array => must match at least one element in array.
				case is_object($value) && $value instanceof Elixir_Array:
					if($def[$field]['type'] != $value->getClass()) {
						require_once 'Elixir/Exception/Constraint/InvalidClass.php';
						throw new Elixir_Exception_Constraint_InvalidClass();
					}
					$adef = $this->_getDefinition($value->getClass());
					
					$or = array();
					foreach($value->getDbRows() as $row) {
						// the columns in the db row belong to a different table
						// so we need to translate the col names first
						$cols = array();
						try {					
							foreach($def[$field]['field'] as $db_col => $src_fields) {
								$src_field = array_shift($src_fields);
								if(is_scalar($adef[$src_field]['field'])) {
									$src_col = $adef[$src_field]['field'];
								}
								else {
									$src_col = array_search($src_fields, $adef[$src_field]['field']);
								}
								// if field was not a scalar and was not found in the search, this will fail.
								// This should not be possible if the field definitions are correct, so we
								// throw a definition error if this occurs.  
								$cols[$db_col] = $row[$src_col];
							}
						}
						catch(ErrorException $e) {
							require_once 'Elixir/Exception/Definition.php';
							throw new Elixir_Exception_Definiton($e->getMessage()); 
						}
						$or[] = '('.$this->_convertColsToCond(array('d'=>$cols)).')';
					}
					$conds[] = '('.implode(' || ', $or).')';
					break;
				// builtin type with multiple values => must match at least one value.
				case is_scalar($def[$field]['field']) && is_array($value):
					$or = array();
					foreach($value as $val) {
						$or[] = $this->_convertColsToCond(array($def[$field]['field'] => $val));
					}
					$conds[] = '('.implode(' || ', $or).')';
					break;
				// builtin type with single value => must equal that value
				case is_scalar($def[$field]['field']) && (is_scalar($value) || is_null($value)):
					$conds[] = $this->_convertColsToCond(array($def[$field]['field'] => $value));
					break;
				// builtin type cannot have an object or resource for a value
				case is_scalar($def[$field]['field']):
					require_once 'Elixir/Exception/Constraint.php';
					throw new Elixir_Exception_Constraint('builtin type cannot have an object or resource for a value');
				case is_scalar($value) && count($def[$field]['field']) == 1:
					$conds[] = $this->_convertColsToCond(array(key($def[$field]['field']) => $value));
					break;
				case is_array($value) && is_string(key($value)):
					$cols = $this->_convertFieldsToCond($def[$field]['field'], $value);
					$conds[] = '('.$this->_convertColsToCond($cols).')';
					break;
				case is_array($value) && is_int(key($value)):
					$or = array();
					foreach($value as $val) {
						$cols = $this->_convertFieldsToCols($def[$field]['field'], $value);
						$or[] = '('.$this->_convertColsToCond($cols).')'; 
					}
					$conds[] = '('.implode(' || ', $or).')';
					break;
				default:
					require_once 'Elixir/Exception/Constraint.php';
					throw new Elixir_Exception_Constraint('unknown constraint');
			}
		}
		$where .= implode(' && ', $conds);
		return $where;
	}
	
	protected function _buildOrder($def, $order) {
		// TODO build order SQL
		return '';
	}
	
	protected function _buildLimit($def, $limit) {
		// TODO build limit SQL
		return '';
	}
	
	protected function _getColNamesFromFields($def, $fields) {
		$colnames = array();
		foreach((array)$fields as $field) {
			$colnames += array_keys($def[$field]['field']);
		}
		return $colnames;
	}
	
	abstract public function execSelect($query);
	
	abstract public function execUpdate($query);
	
	abstract public function execInsert($query);
	
	abstract public function execDelete($query);
	
	protected function _convertObjectToFields($obj, $fields=null) {
		if(!$fields) {
			$fields = call_user_func(array(get_class($obj), 'getPrimaryKeyFields'));
		}

		$field_vals = array();
		// convert selected fields to db row
		foreach((array)$fields as $field) {
			$value = $obj->$field;
			if($value instanceof Elixir_Object) {
				$value = $this->_convertObjectToFields($value);
			}
			$field_vals[$field] = $value;
		}
		return $field_vals;
	}
	
	protected function _convertFieldsToCols($field_def, $fields) {
		$cols = array();
		foreach($field_def as $db_col => $field_q) {
			$value = $fields;
			while($field_q) {
				$field = array_shift($field_q);
				$value = &$value[$field];
				if($value instanceof Elixir_Object) {
					$value = $this->_convertObjectToFields($value);
				}
			}
			$cols[$db_col] = $value;
		}
		return array('d'=>$cols);
	}

	protected function _convertColsToCond($cols) {
		$and = array();
		foreach($cols as $ns => $data) {
			foreach($data as $col => $value) {
				// special case for null values
				if(is_null($value)) {
					$and[] = "isnull($ns.$col)";
				}
				else {
					//quote and add to list
					$and[] = "$ns.$col=" . $this->quote($value); 
				}
			}
		}
		return implode(' && ', $and);
	}
	
}
