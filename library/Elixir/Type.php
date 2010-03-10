<?php
require_once 'Elixir/Session.php';
require_once 'Elixir/Array.php';
require_once 'Elixir/Constraint.php';

abstract class Elixir_Type {
	const STATUS_NEW     = 'new';
	const STATUS_LOADED  = 'loaded';
	const STATUS_UPDATED = 'updated';
	const STATUS_DELETED = 'deleted';
	
	const RELATION_ONE_TO_ONE   = 'one_to_one';
	const RELATION_ONE_TO_MANY  = 'one_to_many';
	const RELATION_MANY_TO_ONE  = 'many_to_one';
	const RELATION_MANY_TO_MANY = 'many_to_many';
	
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

	protected $_values = array();
	protected $_dbvalues = array();
	protected $_session = null;
	
	protected $_status;
	
	protected $_oid;
	
	public function __construct($fields = array(), $session = null, $check_integrity = false) {
		static::_init();
		
		// load the default session if one not provided
		if(!$session) {
			$session = Elixir_Session::getDefaultSession();
		}
		
		$def = &static::$_definition;
		
		// get the primary key names
		$pks = static::getPrimaryKeyFieldNames();
		
		$pks_vals = array();
		foreach($fields as $field => &$val) {
			if($val !== null && in_array($field, $pks)) {
				$pks_vals[$field] = $val;
			}
		}
		
		// separate auto_increment keys
		$pks_auto = array_filter($pks, function($key) use(&$def) {
			return isset($def[$key]['auto_increment']) && $def[$key]['auto_increment'];
		});
		$pks_nonauto = array_diff($pks, $pks_auto);
		
		// all non-auto primary keys must be present
		if(count($pks_nonauto) != count(array_intersect_key(array_flip($pks_nonauto), $pks_vals))) {
			require_once 'Elixir/Exception.php';
			throw new Elixir_Exception(sprintf('All non-auto increment primary keys must be specified (%s).', implode(', ', $pks_nonauto)));
		}
		
		// if all pks are given, check whether another identical obj is loaded.
		// while this does not guarantee no duplicates, it is fast, so nothing lost by trying
		if((count($pks) == count($pks_vals)) && $session->get(static::_generateId($pks_vals))) {
			throw new Elixir_Exception_DuplicateRow();
		}
		// if we need to check integrity, only bother if new and all auto increment pks are set
		if(
			$check_integrity && $new
			&& count($pks_auto) == count(array_intersect_key(array_flip($pks_auto), $fields))
			&& static::get($fields, false, $session)
		) {
			throw new Elixir_Exception_DuplicateRow();
		}
		
		// checks are over, fill up this object with given fields
		foreach($fields as $field => $value) {
			// don't try to set pks to null. will cause error.
			if(in_array($field, $pks_auto) && is_null($value)) {
				continue;
			}
			$this->_set($field, $value, true);
		}
		
		// set object hash (uuid v4)
		$this->_oid = Elixir_Utils::UUIDv4(); 
		
		// set the session and set status
		$this->_session = $session;
		
		$this->_setStatus(static::STATUS_NEW);
		
		$this->_postCreate();
	}

	public function __get($name) {
		// if object is deleted throw exception
		if($this->_status == self::STATUS_DELETED) {
			require_once 'Elixir/Exception/InvalidObject.php';
			throw new Elixir_Exception_InvalidObject('This object has been deleted.');
		}

		// check that the field is valid
		if(!in_array($name, array_keys(static::$_definition))) {
			require_once 'Elixir/Exception/InvalidField.php';
			throw new Elixir_Exception_InvalidField(sprintf(
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
		elseif($value === null && $this->_status == self::STATUS_NEW) {
			$value = static::$_definition[$name]['default']; 
		}
		// otherwise contact the DB to retrieve this info.
		else {
			$value = $this->_loadField($name);
		}
		
		// return the value in the correct type.
		if($value !== null) {
			switch(strtolower(static::$_definition[$name]['type'])) {
				case 'string':
				case 'text':
				case 'blob':
					return (string)$value;
				case 'int':
					return (int)$value;
				case 'float':
					return (float)$value;
				case 'boolean':
					return (boolean)$value;
				case 'date':
					// load object if needed
					if(!$value instanceof DateTime) {
						$value = new DateTime($value);
					}
					break;
				default:
					if(in_array(static::$_definition[$name]['relation'], array(self::RELATION_ONE_TO_ONE, self::RELATION_MANY_TO_ONE))) {
						$class = static::$_definition[$name]['type'];
						// if value is object, return it as is.
						if(is_subclass_of($value, $class)) {
							break; 
						}
						// otherwise, load it from the ids.
						$value = $class::get($value);
					}
					elseif(in_array(static::$_definition[$name]['relation'], array(self::RELATION_ONE_TO_MANY, self::RELATION_MANY_TO_MANY))) {
						$type = static::$_definition[$name]['type'];
						// if value is array of correct type, return as is.
						if($value instanceof Elixir_Array && $value->getType() == $type) {
							break;
						}
						//otherwise load it from db.
						$value = $type::getBy(array($def['remote'] => $this));
						$value->setConditionReadOnly();
					}
			}
			$this->_values[$name] = $value;
			return $value;
		}
		return null;
	}
	
	public function __set($name, $value) {
		$this->_set($name, $value, true);
		$this->_setStatus(self::STATUS_UPDATED);
	}
	
	public function getOID() {
		return $this->_oid;
	}
	
	protected function _set($name, $value, $check_integrity = true) {
		$def = &static::$_definition;
		
		// make sure object is still live
		if($this->_status == self::STATUS_DELETED) {
			require_once 'Elixir/Exception.php';
			throw new Elixir_Exception('Cannot edit deleted object');
		}
		// make sure field is valid.
		if(!isset($def[$name])) {
			require_once 'Elixir/Exception.php';
			throw new Elixir_Exception(sprintf('No such field (%s)', $name));
		}
		
//		$cvalue = isset($this->_values[$name]) ? $this->_values[$name] : null;
		
		// if type is a builtin, save directly to object.
		switch($def[$name]['type']) {
			case 'string':
			case 'text':
			case 'blob':
				$this->_values[$name] = (string)$value;
				break;
			case 'boolean':
				$this->_values[$name] = (boolean)$value;
				break;
			case 'int':
				$this->_values[$name] = (int)$value;
				break;
			case 'float':
				$this->_values[$name] = (float)$value;
				break;
			case 'date':
				if(!$value instanceof DateTime) {
					$value = new DateTime($value);
				}
				$this->_values[$name] = $value;
				break; 
			default:
				// objects of correct type, or thier keys are acceptable.
				do {
					// if relation is one_to_many or many_to_many, not supported yet
					if(in_array($def[$name]['relation'], array(self::RELATION_MANY_TO_MANY, self::RELATION_ONE_TO_MANY))) {
						require_once 'Elixir/Exception/NotImplemented.php';
						throw new Elixir_Exception_NotImplemented();
					}
					
					// if object is valid type, skip further checks
					if(is_object($value) && is_subclass_of($value, $def[$name]['type'])) {
						break;
					}
					// if object other than datetime, it is of the wrong class
					if(is_object($value) && !$value instanceof Datetime) {
						require_once 'Elixir/Exception.php';
						throw new Elixir_Exception('this given object is of the wrong class');
					}
					// if not array, and the field only has one key, normalise to array
					if(!is_null($value) && !is_array($value)) {
						if(count($def[$name]['field']) == 1) {
							$value = array(current(current($def[$name]['field'])) => $value);
						}
						else {
							throw new Elixir_Exception('this field has more than one key');
						}
					}
					// now try to convert to object. exceptions will be thrown if the keys aren't valid
					// this will retieve the object from the db
					if($check_integrity) {
						$value = call_user_func(array($def[$name]['type'], 'get'), $value, $this->_session);
					}
					// if we are 100% sure in our data (have done the check before, skip it)
					else {
						$value = call_user_func(array($def[$name]['type'] , '_create'), $value, $this->_session);
					}
				} while(false);
				
				// and set.
				$this->_values[$name] = $value;
		}
		
		return; //void
	}

	protected function _postCreate() {
	}
	
	/**
	 * Create object without calling the constructor.
	 * 
	 * @return Elixir_Type
	 */
	static protected function _create(array $fields, Elixir_Session $session) {
		// create serialized string of an empty object
		$class = get_called_class();
		$sobject = sprintf('O:%d:"%s":0:{}', strlen($class), $class);
		
		// by unserializing, we avoid calling __construct()
		$object = unserialize($sobject);
		
		// generate oid
		$object->_oid = Elixir_Utils::UUIDv4();
		
		// add properties
		$object->_session = $session;

		// checks are over, fill up this object with given fields
		foreach($fields as $field => $value) {
			$object->_set($field, $value, false);
		}
		
		// set status
		$object->_setStatus(self::STATUS_LOADED);
		
		// call any othe init code that might be needed
		$object->_postCreate();
		
		return $object;
	}
	
	static public function get($id, $fields = false, $reload = false, $session = null) {
		static::_init();
		// get pks
		$pks = static::getPrimaryKeyFieldNames();
		
		// make sure all keys have been given.
		if(!is_array($id) && count($pks) == 1) {
			$id = array(current($pks) => $id);
		}
		elseif(!is_array($id) || count($pks) != count(array_intersect_key(array_flip($pks), $id))) {
			require_once 'Elixir/Exception.php';
			throw new Elixir_Exception('Not all the need keys have been provided');
		}
		
		// load default session if not provided
		if(!$session) {
			$session = Elixir_Session::getDefaultSession();
		}
		
		// if the object is already loaded in the session, then use it.
		if(!$reload && $obj = $session->get(static::_generateId($id))) {
			return $obj;
		}
		
		$constraint = new Elixir_Constraint(get_called_class());
		foreach($id as $field => $value) {
			$constraint->addField($field, $value);
		}

		// normalise/get fields.
		// true => all
		if($fields === true) {
			$fields = array_keys(static::$_definition);
		}
		// false/null/empty => default
		elseif(!$fields) {
			$fields = static::getGroupFieldNames('default');	
		}
		// array/string => selected + pks
		else {
			$fields = array_unique((array)$fields + $pks);
		}
		
		// get the actual db row.
		$rows = static::$_adapter->select(get_called_class(), $fields, $constraint);
		$data = current($rows);
		if(!$data) {
			require_once 'Elixir/Exception/NotFound.php';
			throw new Elixir_Exception_NotFound();
		}
		$values = static::_dbRowToValues($data);

		// merge the ids with the values.
		return static::_create($values, $session);
	}
	
	static public function getBy($constraint = null, $fields = false, $order=null, $session = null) {
		static::_init();
		
		if(!$constraint) {
			$constraint = new Elixir_Constraint(get_called_class());
		}
		elseif(is_array($constraint)) {
			$fields = $constraint;
			$constraint = new Elixir_Constraint();
			foreach($fields as $field => $value) {
				$constraint->addField($field, $value);
			}
			unset($fields);
		}
		if(!$constraint instanceof Elixir_Constraint) {
			require_once 'Elixir/Exception/Constraint';
			throw new Elixir_Exception_Constraint(); 
		}
		
		// normalise/get fields.
		// true => all
		if($fields === true) {
			$fields = array_keys(static::$_definition);
		}
		// false/null/empty => default
		elseif(!$fields) {
			$fields = static::getGroupFieldNames('default');	
		}
		// array/string => selected + pks
		else {
			$fields = array_unique((array)$fields + static::getPrimaryKeyFieldNames());
		}
		
		// load default session if not provided
		if(!$session) {
			$session = Elixir_Session::getDefaultSession();
		}
		
		$array = new Elixir_Array($session);
		$array->setType(get_called_class());
		$array->setConstraint($constraint);
		$array->setFields($fields);
		
		return $array;
	}

	static public function getByDbRow($row, $session, $reload = false) {
		static::_init();
		
		// convert the db values into fields.
		$values = static::_dbRowToValues($row);

		// get id values
		$pks = static::getPrimaryKeyFieldNames();
		$id = array_intersect_key($values, array_flip($pks));
		
		// if not lazy, try and reload values from db first.
		if($reload) {
			return static::get($id, array_keys($values), true, $session);
		}
		
		// check session, returning if found.
		if($obj = $session->get(static::_generateId($id))) {
			return $obj;
		}
		
		// not found, so create new object
		return static::_create($values, $session);
	}
	
	static public function getDefinition() {
		static::_init();
		return static::$_definition;
	}
	
	public function getId() {
		$id = array();
		foreach(static::getPrimaryKeyFieldNames() as $field) {
			$val = $this->$field;
			if($val instanceof Elixir_Type) {
				$val = $val->getId();
			}
			$id[] = $val;
		}
		return $id;
	}
	
	static protected function _generateId($id) {
		if($id instanceof Elixir_Type) {
			$id = $id->getId();
		}
		return get_called_class() .':'. serialize($id);
	}
		
	static protected function _init() {
		// if already initialised, don't do it twice.
		if(static::$_initialised) {
			return true;
		}
		// don't initialise this abstract class.
		if(get_called_class() == __CLASS__) {
			return true;
		}
		
		// init adapter
		static::_initAdapter();
				
		// make sure definition is provided
		if(!static::$_definition || !static::$_table) {
			require_once 'Elixir/Exception/Definition.php';
			throw new Elixir_Exception_Definition('table not defined');
		}
		
		// parent definition should be merged into current class
		static::$_definition = array_merge(
			call_user_func(array(get_parent_class(get_called_class()), 'getDefinition')),
			static::$_definition
		);
		
		static::_initDefinition();
		
		// mark
		return static::$_initialised = true;
	}
	
	static protected function _initAdapter() {
		if(!static::$_adapter) {
			require_once 'Elixir/Exception/Definition.php';
			throw new Elixir_Exception_Definition('no adapter params supplies');
		}
		static::$_adapter = Elixir_Adapter::factory(static::$_adapter);
	}

	static protected function _initDefinition() {
		// normalise definition
		foreach(static::$_definition as $field => &$def) {
			// set defaults
			$defaults = array(
				'field' => $field,
				'group' => false,
				'primary_key' => false,
				'auto_increment' => false,
				'default' => null,
				'relation' => false
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
						$def['field'][$v] = (array)$v;
					}
					else {
						$def['field'][$k] = (array)$v;
					}
				}
			}

			// check def contraints
			// only pks can be auto increment
			if($def['auto_increment'] && !$def['primary_key']) {
				throw new Elixir_Exception_Definition('only primary keys may auto increment');
			}
			// pks must be in the deafult group
			if($def['primary_key']) {
				if(!$def['group']) {
					$def['group'] = 'default';
				}
				elseif($def['group'] != 'default') {
					require_once 'Elixir/Exception/Definition.php';
					throw new Elixir_Exception_Definition('primary keys must be in default group');	
				}
			}
			// check the type
			// builtin types
			if(isset($def['type']) && in_array(strtolower($def['type']), array('string', 'int', 'float', 'text', 'blob', 'boolean', 'date'))) {
				// normalise
				$def['type'] = strtolower($def['type']);
				
				// basic sanity checks
				if(!is_scalar($def['field'])) {
					require_once 'Elixir/Exception/Definition.php';
					throw new Elixir_Exception_Definition('the field of a builtin type must be a scalar (db col name).');
				}
			}
			// if not builtin, must be elixir object relation
			else {
				// check relation
				if(!in_array($def['relation'], array(self::RELATION_ONE_TO_ONE, self::RELATION_ONE_TO_MANY, self::RELATION_MANY_TO_ONE, self::RELATION_MANY_TO_MANY))) {
					require_once 'Elixir/Exception/Definition.php';
					throw new Elixir_Exception_Definition('the relation must be set for non-builtin types');
				}
				// check inheritance
				if(
					!isset($def['type'])
					|| (class_exists($def['type']) && is_subclass_of($def['type'], get_called_class()))
				) {
					require_once 'Elixir/Exception/Definition.php';
					throw new Elixir_Exception_Definition(sprintf('unknown type or class does not inherit from %s', get_class(self))); 
				}
				
				// field must be an array. The key is the db col and the value is the key field name.
				// The field name is an array of consecutive field names, so in the case of a compound key,
				// the array length will be larger than one.
				if(!is_array($def['field'])) {
					require_once 'Elixir/Exception/Definition.php';
					throw new Elixir_Exception_Definition('not builtin type\'s field definition must be an array');
				}
				//TODO stricter check. 
			}
		}
	}
	
	static public function getGroupFieldNames($group) {
		$def = &static::$_definition;
		return array_filter(array_keys($def), function($field) use(&$def, &$group) {
			return $def[$field]['group'] == $group;
		});
	}  

	static protected function _dbRowToValues($row) {
		$def = &static::$_definition;
		
		$values = array();
		foreach($row as $col => $value) {
			// search def for correct field;
			foreach($def as $field => $spec) {
				if($spec['field'] == $col) {
					$values[$field] = $value;
				}
				elseif(is_array($spec['field']) && isset($spec['field'][$col])) {
					if(!isset($values[$field])) {
						$values[$field] = array();
					}
					$target = &$values[$field];
					for($i=0; $i < count($spec['field'][$col]) - 1; $i++) {
						$f = $spec['field'][$col][$i];
						if(!isset($target[$f])) {
							$target[$f] = array();
							$target = &$target[$f];
						}
					}
					$f = $spec['field'][$col][$i];
					$target[$f] = $value;
				}
			}
		}
		return $values;
	}
	
	protected function _loadField($name, $load_group = true, $reload = false) {
		$def = &static::$_definition;
		
		// load every field in group that is not set.
		if($load_group && $reload) {
			$names = static::getGroupFieldNames($def[$name]['group']);
		}
		elseif($load_group) {
			$values = &$this->_values;
			$names = array_filter(static::getGroupFieldNames($def[$name]['group']), function($field) use(&$values) {
				return !array_key_exists($field, $values);
			});
		}
		else {
			$names = array($name);
		}
		
		// remove any fields that are one_to_many/many_to_many as these are remote references
		foreach($names as $n) {
			if(isset($def[$n]['relation']) && in_array($def[$n]['relation'], array(self::RELATION_ONE_TO_MANY, self::RELATION_MANY_TO_MANY))) {
				unset($names[$n]);
			}
		}
		if(!$names) {
			return null;
		}
		
		// use primary keys to constrain to a single row
		$pks = static::getPrimaryKeyFieldNames();
		$constraint = new Elixir_Constraint(get_class($this));
		foreach(array_intersect_key($this->_values, array_flip($pks)) as $field => $value) {
			$constraint->addField($field, $value);
		}
		$rows = static::$_adapter->select(get_class($this), $names, $constraint);
		$data = current($rows);
		if(!$data) {
			require_once 'Elixir/Exception.php';
			throw new Elixir_Exception('No row with given primary keys found');
		}
		
		foreach(static::_dbRowToValues($data) as $field => $value) {
			$this->_set($field, $value, false);
		}
		
		// and finaly return the value that we were asked to load in the first place
		return $this->_values[$name];
	}
	
	protected function _setStatus($status) {
		$this->_status = $status;
		if($this->_session) {
			$this->_session->setStatus($this);
		}
		return $this;
	}
	
	public function getStatus() {
		return $this->_status;
	}
	
	public function getSession() {
		if(is_string($this->_session)) {
			$this->_session = Elixir_Session::getSession($this->_session);
		}
		if(!$this->_session) {
			require_once 'Elixir/Exception.php';
			throw new Elixir_Exception('invalid session id');
		}
		return $this->_session;
	}
	
	static public function getPrimaryKeyFieldNames() {
		$pks = array();
		foreach(static::$_definition as $field => &$def) {
			if($def['primary_key']) {
				$pks[] = $field;
			}
		}
		return $pks;
	}
	
	static public function getTable() {
		static::_init();
		return static::$_table;
	}
	
	static public function getAdapter() {
		static::_init();
		return static::$_adapter;
	}
}