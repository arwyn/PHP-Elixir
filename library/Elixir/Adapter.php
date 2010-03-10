<?php
require_once 'Elixir/Adapter/Interface.php';
require_once 'Elixir/Constraint.php';

abstract class Elixir_Adapter implements Elixir_Adapter_Interface {
	private static $_registry = array();
	
	protected $_params = null;
	
	public function __construct($params) {
		$this->_params = $params;
	}
	
	static function factory($params) {
		if(!isset($params['class']) || !is_subclass_of($params['class'], 'Elixir_Adapter_Interface')) {
			require_once 'Elixir/Exception/Adapter.php';
			throw new Elixir_Exception_Adapter();
		}
		ksort($params);
		$id = serialize($params);
		if(!isset(self::$_registry[$id])) {
			$class = $params['class'];
			unset($params['class']);
			self::$_registry[$id] = new $class($params);
		}
		return self::$_registry[$id];
	}
	
	public function select($type, array $field_names, Elixir_Constraint $constraint) {
		//get object definition
		$def = $this->_getDefinition($type);
		$table = $this->_getTable($type);
		
		if(!$field_names) {
			require_once 'Elixir/Exception/Adapter.php';
			throw new Elixir_Exception_Adapter('fields must be provided');
		}
		$select = $this->_buildSelect($def, $field_names);
		$from = $this->_buildFrom($def, $table, $constraint);
		$bind = array();
		$where = $this->_buildWhere($def, $constraint, $bind);
		
//		$order = $order ? $this->_buildOrder($def, $order) : '';
//		$limit = $limit ? $this->_buildLimit($def, $limit, $offset) : '';
	
		$query = implode(' ', array($select, $from, $where));

		return $this->_execSelect($query, $bind);
	}
	
	public function count($type, Elixir_Constraint $constraint) {
		$def = $this->_getDefinition($type);
		$table = $this->_getTable($type);
		
		$select = $this->_buildSelect($def, false);
		$from = $this->_buildFrom($def, $table, $constraint);
		$bind = array();
		$where = $this->_buildWhere($def, $constraint, $bind);

		$query = implode(' ', array($select, $from, $where));

		$res = $this->_execSelect($query, $bind);
		return (int)$res[0]['count'];
	}
	
	public function update($type, array $fields, Elixir_Constraint $constraint) {
		$def = $this->_getDefinition($class);
		$table = $this->_getTable($class);
		
		$query = implode(' ', array(
			$this->_buildUpdate($def, $table, $constraints),
			$this->_buildSet($def, $data),
			$this->_buildWhere($def, $constraints)
		));
		
		return $this->execUpdate($query);
	} 
	
	public function insert($type, array $fields) {
		$def = $this->_getDefinition($class);
		$table = $this->_getTable($class);
		
		$query = implode(' ', array(
			$this->_buildInsert($table),
			$this->_buildValues($def, $data)
		));
		return $this->execInsert($query);
	}
	
	public function delete($type, Elixir_Constraint $constraint) {
		$def = $this->_getDefinition($class);
		$table = $this->_getTable($class);
		
		$query = implode(' ', array(
			$this->_buildDelete($def, $table, $constraints),
			$this->_buildWhere($def, $constraints)
		));
		return $this->execDelete($query);
	}

	public function normalise($var) {
		$this->init();
		if($var instanceof Datetime) {
			$var = $var->format('Y-m-d H:i:s');
		}
		return $var;
	}
	
	protected function _getDefinition($class) {
		if(!is_subclass_of($class, 'Elixir_Type')) {
			require_once 'Elixir/Exception/InvalidClass.php';
			throw new Elixir_Exception_InvalidClass();
		}
		return call_user_func(array($class, 'getDefinition'));
	}
	
	protected function _getTable($class) {
		if(!is_subclass_of($class, 'Elixir_Type')) {
			require_once 'Elixir/Exception/InvalidClass.php';
			throw new Elixir_Exception_InvalidClass();
		}
		return call_user_func(array($class, 'getTable'));
	}

	protected function _buildSelect($def, $fields) {
		$select = 'SELECT ';

		if(!$fields) {
			$select .= 'COUNT(*) AS count';
		}
		elseif(is_array($fields) || is_string($fields)) {
			$cols = $this->_getColNamesFromFields($def, (array)$fields);
			foreach($cols as $k => $col) {
				$cols[$k] = "d.$col";
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
	
	protected function _buildWhere($def, $constraints, &$bind) {
		$where = 'WHERE ';
		
		$where .= $this->_buildConstraints($def, $constraints, $bind);
		
		return $where;
	}
	
	protected function _buildConstraints($def, $constraints, &$bind) {
		$conds = array();
		foreach($constraints->getFields() as $constraint) {
			extract($constraint); // field, value
			if(!isset($def[$field])) {
				require_once 'Elixir/Exception/Constraint/InvalidField.php';
				throw new Elixir_Exception_Constraint_InvalidField();
			}
			switch(true) {
				// elixir object
				case is_object($value) && $value instanceof Elixir_Type:
					switch(get_class($value)) {
						case $def[$field]['type']:
						case $constraints->getType():
							break;
						default:
							require_once 'Elixir/Exception/Constraint/InvalidClass.php';
							throw new Elixir_Exception_Constraint_InvalidClass();
					}
					$fields = $this->_convertObjectToFields($value);
					$cols = $this->_convertFieldsToCols($def[$field]['field'], $fields);
				
					$conds[] = '('.$this->_convertColsToCond($cols, $bind).')';
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
						$or[] = '('.$this->_convertColsToCond(array('d'=>$cols), $bind).')';
					}
					$conds[] = '('.implode(' || ', $or).')';
					break;
				// builtin type with multiple values => must match at least one value.
				case is_scalar($def[$field]['field']) && is_array($value):
					$or = array();
					foreach($value as $val) {
						$or[] = $this->_convertColsToCond(array('d'=>array($def[$field]['field'] => $val)), $bind);
					}
					$conds[] = '('.implode(' || ', $or).')';
					break;
				// builtin type with single value => must equal that value
				case is_scalar($def[$field]['field']) && (is_scalar($value) || is_null($value)):
					$conds[] = $this->_convertColsToCond(array('d'=>array($def[$field]['field'] => $value)), $bind);
					break;
				// builtin type cannot have an object or resource for a value
				case is_scalar($def[$field]['field']):
					require_once 'Elixir/Exception/Constraint.php';
					throw new Elixir_Exception_Constraint('builtin type cannot have an object or resource for a value');
				case is_scalar($value) && count($def[$field]['field']) == 1:
					$conds[] = $this->_convertColsToCond(array(key($def[$field]['field']) => $value), $bind);
					break;
				case is_array($value) && is_string(key($value)):
					$cols = $this->_convertFieldsToCond($def[$field]['field'], $value);
					$conds[] = '('.$this->_convertColsToCond($cols, $bind).')';
					break;
				case is_array($value) && is_int(key($value)):
					$or = array();
					foreach($value as $val) {
						$cols = $this->_convertFieldsToCols($def[$field]['field'], $value);
						$or[] = '('.$this->_convertColsToCond($cols, $bind).')'; 
					}
					$conds[] = '('.implode(' || ', $or).')';
					break;
				default:
					require_once 'Elixir/Exception/Constraint.php';
					throw new Elixir_Exception_Constraint('unknown constraint');
			}
		}
		foreach($constraints->getConstraints() as $constraint) {
			$conds[] = '('.$this->_buildConstaint($def, $constraint, $bind) . ')';
		}
		return $conds ? implode(($constraints->getOp() == Elixir_Constraint::OP_AND) ? ' && ' : ' || ', $conds) : 1;
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
			if(is_array($def[$field]['field'])) {
				$colnames = array_merge($colnames, array_flip(array_keys($def[$field]['field'])));
			}
			else {
				$colnames = array_merge($colnames, array_flip((array)$def[$field]['field']));
			}
		}
		return array_keys($colnames);
	}
	
	protected function _convertObjectToFields($obj, $fields=null) {
		if(!$fields) {
			$fields = call_user_func(array(get_class($obj), 'getPrimaryKeyFieldNames'));
		}

		$field_vals = array();
		// convert selected fields to db row
		foreach((array)$fields as $field) {
			$value = $obj->$field;
			if($value instanceof Elixir_Type) {
				$value = $this->_convertObjectToFields($value);
			}
			$field_vals[$field] = $value;
		}
		return $field_vals;
	}
	
	protected function _convertFieldsToCols($field_def, $fields) {
		$cols = array();
		if(is_array($field_def)) {
			foreach($field_def as $db_col => $field_q) {
				$value = $fields;
				while($field_q) {
					$field = array_shift($field_q);
					$value = &$value[$field];
					if($value instanceof Elixir_Type) {
						$value = $this->_convertObjectToFields($value);
					}
				}
				$cols[$db_col] = $value;
			}
		}
		else {
			$cols[$field_def] = current($fields);
		}
		return array('d'=>$cols);
	}

	protected function _convertColsToCond($cols, &$bind) {
		$and = array();
		foreach($cols as $ns => $data) {
			foreach($data as $col => $value) {
				// special case for null values
				if(is_null($value)) {
					$and[] = "isnull($ns.$col)";
				}
				else {
					//quote and add to list
					$value = $this->normalise($value);
					$and[] = "$ns.$col=?";
					$bind[] = $value; 
				}
			}
		}
		return implode(' && ', $and);
	}
	
	abstract protected function _execSelect($query, $bind);
	abstract protected function _execUpdate($query, $bind);
	abstract protected function _execInsert($query, $bind);
	abstract protected function _execDelete($query, $bind);
}
