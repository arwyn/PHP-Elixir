<?php 
require_once 'Elixir/Db/Query/Interface.php';

class Elixir_Db_Query_Zend implements Elixir_Db_Query_Interface {
	protected $_cols = array();
	protected $_where = array();
	protected $_from = array();
	
	public function __construct(Elixir_Db_Adapter_Zend $adapter) {
		$this->_adapter = $adapter;
	}
	
	public function setAffectedColumn($column, $affected = true) {
		foreach((array)$column as $col) {
			$this->_cols[$col] = $affected;
		}
		return $this;
	}
	
	public function getAffectedColumns() {
		return array_keys(array_filter($this->_cols));
	}
	
	public function where($column, $var = null, $bind = true) {
		$this->_where[] = compact('column', 'var', 'bind');
		return $this;
	}
	
	public function from($table, $cols) {
		$this->_from[$table] = $cols;
		return $this;
	}
	
	public function fetchRow() {
		return $this->_adapter->fetchRow($this);
	}
	
	public function fetchAssoc() {
		return $this->_adapter->fetchAssoc($this);
	}
	
	public function fillZendSelect($select) {
		$select->from(key($this->_from), current($this->_from));
		
		$quote = function($value) use($select) {
			// normalise to values quoter understands.
			switch(true) {
				case is_bool($value):
					$value = (int)$value;
					break;
				case $value instanceof Datetime:
					$value = $value->format('Y-m-d H:i:s');
					break;
			}
			return $select->getAdapter()->quote($value);
		};
		
		foreach($this->_where as &$w) {
			extract($w); // bind, var, column
			switch(true) {
				// do not quote, pass through as is.
				case !$bind:
					$select->where($column);
					break;
				// value should be null
				case is_scalar($column) && is_null($var):
					$select->where('isnull('.$column.')');
					break;
				// simple column, use zend select quoting 
				case is_scalar($column):
					$select->where($column.'=?', $var);
					break;
				// elixir object, so expand before adding to constraints
				case is_object($column) && $column instanceof Elixir_Object:
					$cond = $this->_convertObjectToCond($column, $quote);
					$select->where($cond);
					break;
				// elixir array, so expand before adding to constraints
				case is_object($column) && $column instanceof Elixir_Array:
					$or = array();
					foreach($column->getDbRows() as $row) {
						$or[] = '('.$this->_convertRowToCond($row, $quote).')';
					}
					$select->where(implode(' || ', $or));
					break;
				// use the specified fields of given elixir object as constraints 
				case is_array($column) && is_int(key($column)) && $var instanceof Elixir_Object:
					$select->where($this->_convertObjectFieldsToCond($var, $column, $quote));
					break;
				// expand row to constraints. 
				case is_array($column):
					$select->where($this->_convertRowToCond($column, $quote));
					break;
				default:
					throw new Elixir_Exception('unsupported column type');
			}
		}
	}
	
	protected function _convertObjectToCond($obj, $quote) {
		$pks = call_user_func(array(get_class($obj), 'getPrimaryKeyFields'));
		return $this->_convertObjectFieldsToCond($obj, $pks, $quote);
	}
	
//	protected function _convertFieldToRow($field, $value) {
//		$def = call_user_func(array(get_class($obj), 'getDefinition'));
//		$row = array();
//		
//		if($field_value instanceof Elixir_Object) {
//			$field_value = $this->_convertObjectToFields($field_value, $quote);
//		}
//		
//		// TODO map the field values to db col names
//		return $this->

//		$row = array();
//		return $row;
//	}
	
//	protected function _convertFieldToCond($field, $field_value, $quote) {
//		$row = $this->_convertFieldToRow($field, $field_value);
//		return $this->_convertRowToCond($row, $quote);
//	}
	
	protected function _convertObjectFieldsToCond($object, $fields, $quote) {
		$def = call_user_func(array(get_class($obj), 'getDefinition'));
		$row = array();
		// convert selected fields to db row
		foreach((array)$fields as $field) {
			$value = $object->$field;
			if($value instanceof Elixir_Object) {
				foreach($def[$field]['field'] as $db_col => $target_fields) {
					foreach($target_fields as $target_field) {
						$value = $value->$target_field;
					}
					$row[$db_col] = $value;
				}
			}
			else {
				$row[key($def[$field]['field'])] = $value;
			}
		}
		return $this->_convertRowToCond($row, $quote);
	}
	
	protected function _convertRowToCond($row, $quote) {
		$and = array();
		foreach($row as $col => $value) {
			// special case for null values
			if(is_null($value)) {
				$and[] = 'isnull('.$col.')';
			}
			else {
				//quote and add to list
				$and[] = $col .'='. call_user_func($quote, $value); 
			}
		}
		return implode(' && ', $and);
	}
}