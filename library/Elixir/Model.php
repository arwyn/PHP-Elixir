<?php
namespace Elixir;

use Exception\Model as Exception;

require_once 'Exception.php';
require_once 'Query.php';
require_once 'Session.php';

class Model {
	const LAZYLOAD = 1;
	
	private static abstract $_elixir_config = null;

	protected $_elixir_container = null;
	protected $_elixir_session = null;
	protected $_elixir_id = null;

	public function __construct($data = null, $options = 0, $session = null) {
		static::_init();
		
		$this->_elixir_session = $session;
		
		Session::get($session)->addActivity('new', $this);
		foreach($data as $property => $value) {
			$this->$property = $value;
		}
	}
	
	public function __get($name) {
		if(!isset(static::$_elixir_config['properties'][$name])) {
			throw new Exception('Invalid Property', Exception::INVALID_PROPERY);
		}
		
		return $this->getProperty($name, static::$_elixir_config['properties'][$name]['model']);
	}
	
	public function __set($name, $value) {
		if(!isset(static::$_config_properties[$name])) {
			throw new ModelException('Invalid Property', Exception::INVALID_PROPERY);
		}
		
		$model_class = static::$_config_properties[$name]['model'];
		if($model_class && !in_array($model_class, array('int','float','double','bool','boolean','string'))) {
			if($value instanceof $model_class) {
				$value = $value->getId();
			}
			else {
				$config = $model_class::getDbConfig();
				$value = static::_normaliseId($config['id']['property'], $value);
			}
		}
		if(!isset($this->_properties[$name]) || $this->_properties[$name] = $value) {
			$this->_properties[$name] = $value;
			$this->_changes[$name] = true;
			Session::get($this->_session)->addActivity('update', $this);
		}
	}
	
	protected static function __get_state($data) {
		static::_init();

		$data = array_merge(array(
			'id' => null,
			'session' => null,
			'properties' => array()
		), $data);
		
		if(!isset($data['id'])) {
			throw new ModelException('Id not given', Exception::QUERY_FIELD_COUNT_INCORRECT);
		}
		
		extract($data); // id, session, properties
		
		if(!is_array($id)) {
			$id = array($id);
		}
		
		$id_assoc = static::_normaliseId(static::$_config_db['id']['property'], $id);
		
		if(array_diff_keys($properties, static::$_config_properties)) {
			throw new ModelException('Invalid property given', Exception::INVALID_PROPERTY);
		}
		
		$class = get_called_class();
		$model = unserialize('O:'.count($class).'"'.$class.'":0:{}');
		
		$model->_id = $id_assoc;
		$model->_session = $session;
		$model->_properties = $properties;
		
		return $model;
	}
	
	public function getId($full = false) {
		if(!$full && $this->_id && count($this->_id) == 1) {
			return current($this->_id);
		}
		return $this->_id;
	}

	public function getProperty($name, $as = null) {
		if(!isset(static::$_elixir_config['properties'][$name])) {
			throw new Exception('Invalid Property', Exception::INVALID_PROPERY);
		}
		
		$property = $this->_elixir_loadProperty($name);
		
		switch($as) {
			case null:
				break;
			case 'int':
				$property = (int)current($property);
				break;
			case 'float':
			case 'double':
				$property = (float)current($property);
				break;
			case 'bool':
			case 'boolean':
				$property = (boolean)current($property);
				break;
			case 'string':
				$property = (string)current($property);
				break;
			default:
				$query = array();
				foreach(static::$_elixir_config['properties'][$name]['fields'] as $local => $ref) {
					$query[$ref] = $property[$local];
				}
				$property = $name::get($query, $this->_elixir_session, static::LAZYLOAD);
				break;
		}
		return $property;
	}
	
	public function getChanges() {
		$keys = array_filter($this->_elixir_container['changes']);
		return array_intersect_keys($this->_elixir_container['fields'], array_flip($keys));
	}
	
	public static function get($query, $session = null, $options = 0) {
		static::_init();

		static::_normaliseId($properties, $id)
		$model = static::__get_state(array($query, 'session' => $session));
		
		if($options & static::LAZYLOAD) {
			$model->_properties = $id_assoc;
		}
		else {
			$model->_loadProperty(array_keys($id_assoc));
		}
		
		return $model;
	}
	
	public static function query($constraint, $session = null, $options = 0) {
		static::_init();
	}
	
	public static function getConfigDb() {
		static::_init();
		return static::$_config_db;
	}
	
	public static function getConfigProperties() {
		static::_init();
		return static::$_config_properties;
	}
	
	protected static function _init() {
		if(!static::$_config_db) {
			static::_initConfigDb();
		}
		if(!static::$_config_properties) {
			static::_initConfigProperties();			
		}
	}
	
	protected static function _initConfigDb() {
		if(!static::_config_db) {
			throw new ModelException('Missing db config', Exception::CONFIG_MISSING);
		}
	}
	
	protected static function _initConfigProperties() {
		if(!static::_config_properties) {
			throw new ModelException('Missing property config', Exception::CONFIG_MISSING);
		}
	}
	
	private static function _elixir_getIdFromFields($properties) {
		$id_assoc = array();
		foreach($id as $k => $v) {
			if(is_int($k)) {
				$k = $properties[$k];
			}
			$id_assoc[$k] = $v;
		}
		if(count(array_intersect_keys($id_assoc, array_flip($properties))) != count($properties)) {
			throw new Exception('Not all key properties given', Exception::QUERY_FIELD_COUNT_INCORRECT);
		}
		return $id_assoc;
	}
	
	/**
	 * Load property(ies) from Db
	 * 
	 * @param string|array $names Property(ies) to load
	 * @param boolean $reload If true, will overwrite exiting values
	 * @throws ModelException
	 */
	protected function _loadProperty($names, $reload = false) {
		$select_properties = (array)$names;
		
		foreach((array)$names as $name) {
			if(!isset($this->_config_db[$name])) {
				throw new ModelException('Invalid property', Exception::CONFIG_INCOMPLETE);
			}
			$select_properties = array_merge($select_properties, static::$_config_db['byProperty'][$name]['group']);
		}
		
		if(!$reload) {
			$select_properties = array_diff($select_properties, array_keys($this->_properties));
		}
		$select = array();
		foreach(array_unique($select_properties) as $property) { 
			$select = array_merge($select, (array)static::$_config_db['byProperty'][$property]['field']);
		}
		
		$query = new Query($this->_session);
		$query->select($select);
		$query->where($this->getId(true));
		$result = $query->fetchAssoc();
		
		$count = count($result);
		if(!$count) {
			return null;
		}
		elseif($count > 1) {
			throw new ModelException('More than one result for unique entry', Exception::QUERY_NOT_UNIQUE);
		}
		
		$properties = array();
		foreach(current($result) as $field => $value) {
			$property = static::$_config_db['byField'][$field]['property'];
			if(!isset($properties[$property])) {
				$properties[$property] = array();
			}
			$properties[$property][$field] = $value;
		}
		
		foreach($properties as $property => $value) {
			if(!isset($this->_properties[$name]) || $reload) {
				if(count($value) !== count((array)static::$_config_db['byProperty'][$property]['field'])) {
					throw new ModelException('Field count does not match config', Exception::QUERY_FIELD_COUNT_INCORRECT);
				}
				if(count($value) == 1) {
					$value = current($value);
				}
				$this->_properties[$name] = current($value);
				$this->_changes[$name] = false;
			}
		}
	}		
}
