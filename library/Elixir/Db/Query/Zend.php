<?php 

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
		$this->_where[] = array($column, $var, $bind);
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
		
		foreach($this->_where as $where) {
			$column = $where[0];
			$var    = $where[1];
			$bind   = $where[2];
			switch(true) {
				case !$bind:
					$select->where($column);
					break;
				case is_scalar($column) && is_null($var):
					$select->where('isnull('.$column.')');
					break;
				case is_scalar($column):
					$select->where($column, $var);
					break;
				case is_object($column) && $column instanceof Elixir_Object:
					$cond = $this->_convertObjectToCond($column, $select);
					$select->where($cond);
					break;
				case is_object($column) && $column instanceof Elixir_Array:
					$or = array();
					foreach($column->getDbRows() as $row) {
						$or[] = '('.$this->_convertRowToCond($row, $select).')';
					}
					$select->where(implode(' || ', $or));
					break;
				case is_array($column) && is_int(key($column)):
					$or = array();
					foreach($column as $col) {
						$or[] = '('.$this->_convertFieldsToCond($col).')';
					}
					$select->where(implode(' || ', $or));
					break;
				case is_array($column):
					$select->where($this->_convertFieldsToCond($column));
					break;
				default:
					throw new Elixir_Exception('unsupported column type');
			}
		}
	}
	
	protected function _convertObjectToCond($obj, $select) {
		$row = array();
		foreach(call_user_func(array(get_class($obj), 'getPrimaryKeyFields')) as $field) {
			array_merge($row, $this->_convertFieldToRow($field, $obj->$field));
			$fields[] = ($val instanceof Elixir_Object) ? $this->_convertObjectToFields($val) : $val;
		}
		
		$fields = $this->_convertObjectToFields($obj);
		return $this->_convertFieldsToCond($fields, $select);
	}
	
	protected function _convertFieldToRow($obj) {
		$fields = array();
		return $fields;
	}
	
	protected function _convertFieldsToCond($fields, $select) {
//		$def = call_user_func(array(get_class($obj), 'getDefinition'));
		$row = array();
		foreach($fields as $field => $value) {
			if($value instanceof Elixir_Object) {
				$value = $this->_convertObjectToFields($object);
			}
			array_merge($row, $this->_convertFieldToRow($field, $value));
		}
	}
}