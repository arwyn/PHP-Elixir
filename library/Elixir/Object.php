<?php
abstract class Elixir_Object {
	const STATUS_NEW     = 'new';
	const STATUS_LOADED  = 'loaded';
	const STATUS_UPDATED = 'updated';
	const STATUS_DELETED = 'deleted';
	
	/**
	 * Has the class been initialised before?
	 * @var boolean
	 */
	static protected $_initialised = false;
	
	/**
	 * The object(table) definition.
	 * Must be an array in the following syntax:
	 * { field: field_def, .. }
	 * 
	 * field: The name of a field
	 * field_def: The field definition in the following format.
	 *	{
	 * 		'field': db_field|[db_field,..]|{db_field: rel_field, ..},
	 * 		'type': builtin_type|elixir_class,
	 * 		'primary_key': boolean,
	 * 		'auto_increment': boolean,
	 * 		'group': string,
	 * 		'default': mixed
	 *  }
	 *  db_field: (string) the name of the db column.
	 *  rel_field: (string|array) the field name (or hirarchy) in the related elixir object.
	 *  builtin_type: ('string'|'int'|'boolean'|'float'|'date'|'text'|'blob') built-in type
	 *  elixir_class: (string) the name of an elixir class
	 *  
	 *  Notes:
	 *   field & type are required.
	 *   only primary keys can auto-increment.
	 *   a group of null == 'default' 
	 * 
	 * @var array
	 */
	static protected $_definition = array();
	static protected $_table = false;

	static protected $_adapter = false;

	protected $_init = false; 
	protected $_values = array();
	protected $_dbvalues = array();
	protected $_session = null;
	protected $_adapter = null;
	
	public function __constuct($session = null) {
		static::_init();
		
		// load the default session if one not provided
		if(!$session) {
			$session = static::_getDefaultSession();
		}
		$this->_session = $session;
		$this->_init = false;
	}
	
	public static function add($fields = array(), $check_integrity = false, $new = true, $session = null) {
		static::_init();
		
		$def = &static::$_definition;
		
		// get the primary key names
		$pks = static::_getPrimaryKeyFields();
		
		// separate auto_increment keys
		$pks_auto = array_map($pks, function($key) use(&$def) {
			return isset($def[$key]['auto_increment']) && $def[$key]['auto_increment'];
		});
		$pks_nonauto = array_diff($pks, $pks_auto);
		
		// all non-auto primary keys must be present
		if(count($pks_nonauto) != array_intersect_key(array_flip($pks_nonauto), $fields)) {
			throw new Elixir_Exception(sprintf('All non-auto increment primary keys must be specified (%s).', implode(', ', $pks_nonauto)));
		}
		
		
		// if we need to check integrity, only bother if new and all auto increment pks are set
		if(
			$check_integrity && $new
			&& count($pks_auto) == count(array_intersect_key(array_flip($pks_auto), $fields))
			&& static::get($fields, false, $session)
		) {
			throw new Elixir_Exception('Could not create object: duplicate primary keys.');
		}

		// try and get object with this id from the session
		$obj = 1;
		// now fill up the properties
		foreach($fields as $field => $value) {
			$this->$field = $value;
		}
		
		$this->_setStatus($new ? static::STATUS_NEW : static::STATUS_LOADED);
	}

	public function __get($name) {
		// if object is deleted throw exception
		if($this->_status == self::STATUS_DELETED) {
			throw new Elixir_Exception('This object has been deleted.');
		}

		// check that the field is valid
		if(!in_array($name, array_keys($definition))) {
			throw new Elixir_Exception(sprintf(
				'No such field %s in table %s',
				static::$_definition,
				static::$_table
			));
		}
		
		// get the value id.
		$value = null;
		
		// if we have the value cached, use that value.
		if(array_key_exists($name, $this->_values)) {
			$value = $this->_values[$name];
		}
		// if we are new object, return the default.
		if($this->_status == self::STATUS_NEW) {
			$value = static::$_definitions[$name]['default']; 
		}
		// otherwise contact the DB to retrieve this info.
		else {
			$value = $this->_loadField($name);
		}
		
		// return the value in the correct type.
		if($value !== null) {
			switch(strtolower(static::$_definition[$name]['type'])) {
				case 'string':
					return (string)$value;
				case 'int':
					return (int)$value;
				case 'float':
					return (float)$value;
				case 'boolean':
					return (boolean)$value;
				case 'date':
					return new DateTime($value);
				default:
					$class = static::$_definition[$name]['type'];
					return $class::get($value);
			}
		}
		return null;
	}
	
	public function __set($name, $value) {
		$def = &static::$_definition;
		
		// make sure object is still live
		if($this->_status == 'deleted') {
			throw new Elixir_Exception('Cannot edit deleted object');
		}
		// make sure field is valid.
		if(!isset($def[$name])) {
			throw new Elixir_Exception('No such field');
		}
		
		// if type is a builtin, save directly to object.
		switch($def[$name]['type']) {
			case 'string':
			case 'text':
			case 'blob':
				$this->_values[$name] = (string)$value;
				break;
			case 'date':
				//todo
			case 'int':
			case 'boolean':
				$this->_values[$name] = (int)$value;
				break;
			case 'float':
				$this->_values[$name] = (float)$value;
				break;
			default:
				// normalise.
				if(is_subclass_of($value, $def[$name])) {
					$values = array();
					foreach($def[$name]['field'] as $db_field => $rel_field) {
						$val = $value;
						foreach((array)$rel_field as $sub_field) {
							$val = $val->$sub_field;
						}
						$values[$db_field] = $val;
					}
					$value = $values;
				}

				// make sure the number of keys match.
				// this is the only check we do here, if they are of the wrong type
				// an error will (hopefully) be thrown by the DB.
				if(count($def[$name]['field']) != count(array_intersect_key($def[$name]['field'], $value))) {
					throw new Elixir_Exception('the given arrays keys do not match the definition for this field');
				}
				
				// and set.
				$this->_values[$name] = $value;
		}
		// set the status to modified
		$this->_setStatus(static::STATUS_UPDATED);
		
		return; //void
	}
	
	static public function get($id, $full = false, $session = null) {
		static::_init();
		// get pks
		$pks = static::_getPrimaryKeyNames();
		
		// make sure all keys have been given.
		if(!is_array($id) && count($pks) == 1) {
			$id = array(key($pks) => $id);
		}
		elseif(!is_array($id) || count($pks) != count(array_intersect_keys(array_flip($pks), $id))) {
			throw new Elixir_Exception('Not all the need keys have been provided');
		}
		
		// load default session if not provided
		if(!$session) {
			$session = static::_getDefaultSession();
		}
		
		// if the object is already loaded in the session, then use it.
		if($obj = $session->get(static::_generateId($id))) {
			return $obj;
		}
		
		$db = static::getAdapter();
		$select = $db->select();
		
		static::_buildSelect($select, static::_getGroupFields('default'), $id);
		
		// get the actual db row.
		$data = $db->fetchRow($select);
		if(!$data) {
			throw new Elixir_Exception('No row with given primary keys found');
		}
		
		$values = static::_dbRowToValues($data);
				
		return new static($values, false, false, $session);
	}
	
	static public function getBy($fields = array(), $order = array(), $full = false) {
		static::_init();
		
		$def = &static::getDefinition();
		
		// get only the fields that are actually in this object
		$fields = array_intersect_keys($fields, $def);
		
		// get eveything in the default group
		$select_fields = static::_getGroupFields('default');
		
		$db = static::getAdapter();
		$select = $db->select();
		
		static::_buildSelect($select, $select_fields, $fields);
		
		// get the db rows.
		$values = $db->fetchAssoc($select);
		foreach($values as $k=>&$row) {
			$values[$k] = static::_dbRowToValues($row);
		}
		
		$array = new Elixir_Array($values);
		$array->setType(get_called_class());
		
		return $array;
	}

	static public function getDefinition() {
		static::_init();
		return static::$_definition;
	}
	
	public function save($use_transaction = true) {
		// TODO
	}
	
	public function getChanges() {
		
	}
	
	public function getId() {
		$id = array_intersect_keys($this->_values, static::_getPrimaryKeyFields());
		 return static::_generateId($id);
	}
	
	static protected function _init() {
		// if already initialised, don't do it twice.
		if(static::$_initialised) {
			return true;
		}
		
		// make sure definition is provided
		if(!static::$_definition || !static::$_table) {
			throw new Elixir_Exception(sprintf('Table not defined'));
		}
		
		// parent definition should be merged into current class
		static::$_definition = array_merge(parent::getDefinition(), static::$_definition);
		
		// init adapter
		static::getAdapter();
				
		// normalise definition
		foreach(static::$_definition as $field => &$def) {
			// set defaults
			$defaults = array(
				'field' => $field,
				'group' => 'default',
				'primary_key' => false,
				'auto_increment' => false,
				'default' => null
			);
			foreach($defaults as $key => $default) {
				if(!isset($def[$key])) {
					$def[$key] = $default;
				}
			}
			if(is_array($def['field'])) {
				foreach($def['field'] as $k => $v) {
					if(is_int($k)) {
						unset($def['field'][$k]);
						$def['field'][$v] = $v;
					}
				}
			}

			// check def contraints
			if($def['auto_increment'] && !$def['primary_key']) {
				throw new Elixir_Exception('Only primary keys may auto increment');
			}
			if(isset($def['type']) && in_array(str2lower($def['type']), array('string', 'int', 'float', 'text', 'blob', 'boolean', 'date'))) {
				$def['type'] = strtolower($def['type']);
			}
			elseif(
				!isset($def['type'])
				|| (class_exists($def['type']) && is_subclass_of($def['type'], get_class(self)))
			) {
				throw new Elixir_Exception(sprintf('Unknown type or class does not inherit from %s', get_class(self))); 
			}
		}
		
		// mark
		return static::$_initialised = true;
	}
	
	static protected function _getGroupFields($group) {
		$def = &static::$_definition;
		return array_filter(array_keys($def), function($field) use(&$def, &$group) {
			return $def[$field]['group'] == $group;
		});
	}  
	
	protected function _loadField($name, $load_group = true, $reload = false) {
		$def = &static::$_definition;
		$values = &$this->_values;
		
		// load every field in group that is not set.
		if($load_group && $reload) {
			$names = static::_getGroupFields($def[$name]['group']);
		}
		elseif($load_group) {
			$names = array_filter(static::_getGroupFields($def[$name]['group']), function($field) use(&$values) {
				return !array_key_exists($field, $values);
			});
		}
		else {
			$names = array($name);
		}
		
		// use primary keys to constrain to a single row
		$pks = static::_getPrimaryKeyFields();
		$keys = array_intersect_keys(array_flip($pks), $values);
		
		$db = static::getAdapter();
		$select = $db->select();
		
		static::_buildSelect($select, $names, $keys);
		
		// get the actual db row.
		$data = $db->fetchRow($select);
		if(!$data) {
			throw new Elixir_Exception('No row with given primary keys found');
		}
		
		$res_values = static::_dbRowToValues($data);
		
		// cache the values
		$this->_values = array_merge($values, $res_values);
		$this->_dbvalues = array_merge($this->_dbvalues, $res_values);
		
		// and finaly return the value that we were asked to load in the first place
		return $values[$name];
	}
	
	protected function _setStatus($status) {
		if($this->_session) {
			$this->_session->setStatus($this, $status);
		}
		$this->_status = $status;
		return $this;
	}
	
	static protected function _buildSelect(&$select, $select_fields, $where_fields) {
		$def = &static::$_definition;
		
		// add the select part
		$cols = array();
		foreach($select_fields as $field) {
			if(is_array($def[$field]['field'])) {
				$cols = array_merge($cols, $def[$field]['field']);
			}
			else {
				$cols[$def[$field]['field']] = true;
			}
		}
		$cols = array_keys($cols);
		$select->from(static::$_table, $cols);

		// add the where part
		$where_cols = array();
		foreach($where_fields as $field => $value) {
			// if the value is an elixir object, then expand it accoringly.
			if($value instanceof self && class_exists($def[$field]['type'])) {
				$vals = array();
				foreach($def[$field]['field'] as $db_field => $rel_fields) {
					$val = $value;
					foreach((array)$rel_fields as $rel_field) {
						$val = $val->$rel_field;
					}
					$vals[$db_field] = $val;
				}
				$value = $vals;
			}
			// if the value is an elixir array object, then the build the 'or' list.
			elseif($value instanceof Elixir_Array && class_exists($value->getType())) {
				$values = $value;
				$or = array();
				foreach($values as $value) {
					$vals = array();
					$and = array();
					foreach($def[$field]['field'] as $db_field => $rel_fields) {
						$val = $value;
						foreach((array)$rel_fields as $rel_field) {
							$val = $val->$rel_field;
						}
						$vals[$db_field] = $val;
					}
					foreach($vals as $db_field => $val) {
						$and[] = $select->getAdapter()->quote(sprintf('%s=?', $db_field), $val);
					}
					$or[] = '(' . implode(' && ', $and) . ')';
				}
				$select->where('(' . implode(' || ', $or) . ')');
				continue;
			}
			if(is_array($def[$field]['field'])) {
				// get only the correct fields
				$value = array_intersect_keys((array)$value, $def[$field]['field']);
				if(count($value) != count($def[$field]['field'])) {
					throw new Elixir_Exception('Incorrect field id given.');
				}
				$where_cols = array_merge($where_cols, $value);
			}
			else {
				$where_cols[$def[$field]['field']] = $value;
			}
		}
		foreach($where_cols as $key => $value) {
			$select->where(sprintf('%s=?', $key), $value);
		}
	}
	
	static protected function _dbRowToValues($data) {
		$def = &static::$_definition;
		
		$values = array();
		foreach($data as $col => $value) {
			// search def for correct field;
			foreach($def as $field => $spec) {
				if($spec['field'] == $col) {
					$values[$field] = $value;
				}
				elseif(is_array($spec['field']) && isset($spec['field'][$col])) {
					if(!isset($values[$field])) {
						$values[$field] = array();
					}
					$values[$field][$spec['field'][$col]] = $value;
				}
			}
		}
		return $values;
	}
	
	static protected function _getPrimaryKeyFields() {
		$pks = array();
		foreach(static::$_definition as $field => &$def) {
			if($def['primary_key']) {
				$pks[] = $field;
			}
		}
		return $pks;
	}
	
	static protected function _generateId($id) {
		// TODO
		return '';
	} 
//	public function _call($name) {
//		
//	}
}
