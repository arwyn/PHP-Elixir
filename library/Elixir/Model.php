<?php
namespace Elixir;

class Model {
	private static abstract $_config_db = null;
	private static abstract $_config_properties = null;
	
	protected $_session = null;
	protected $_id = null;
	protected $_properties = null;

	public function __construct($data, $options = 0, $session = null) {
		static::_init();
		
		$this->_session = $session;
	}
	
	public function __get($name) {
		if(!isset($this->_properties[$name])) {
			$this->_loadProperty($name);
		}
		if(!isset(static::_config_properties[$name])) {
			throw new Exception('Invalid Property', Exception::INVALID_PROPERY);
		}
		
		$property = $this->_properties[$name];
		if($property !== null) {
			switch($model = static::_config_properties[$name]['model']) {
				case null:
					break;
				case 'int':
					$property = (int)$property;
					break;
				case 'float':
				case 'double':			
					$property = (float)$property;
					break;
				case 'bool':
				case 'boolean':			
					$property = (boolean)$property;
					break;
				case 'string':
					$property = (string)$property;
					break;
				default:
					$property = $model::get(array('id'=>$property), $this->_session);
					break;
			}
		}
		
		return $property;
	}
	
	public function __set($name, $value) {
	}
	
	protected static function __get_state($data) {
		static::_init();
		
	}
	
	public function getId() {
		return $this->_id;
	}
	
	public function static get($query, $session = null, $options = 0) {
		static::_init();
	}
	
	public function static query($constraint, $session = null, $options = 0) {
		static::_init();
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
			throw new Exception('Missing db config', Exception::CONFIG_MISSING);
		}
	}
	
	protected static function _initConfigProperties() {
		if(!static::_config_properties) {
			throw new Exception('Missing property config', Exception::CONFIG_MISSING);
		}
	}
	
	protected function _loadProperty($name) {
		if(!isset($this->_config_db[$name])) {
			throw new Exception('Invalid property', Exception::CONFIG_INCOMPLETE);
		}
		
		$select = (array)static::$_config_db['byProperty'][$name]['field'];
		foreach(static::$_config_db['byProperty'][$name]['group'] as $property) {
			$select = array_merge($select, (array)static::$_config_db['byProperty'][$property]['field']);
		}
		
		$query = new Query($this->_session);
		$query->select($select);
		$query->where($this->getId());
		$result = $query->fetchAssoc();
		
		$count = count($result);
		if(!$count) {
			return null;
		}
		elseif($count > 1) {
			throw new Exception('More than one result for unique entry', EXCEPTION::QUERY_NOT_UNIQUE);
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
			if(!isset($this->_properties[$name])) {
				if(count($value) !== count((array)static::$_config_db['byProperty'][$property]['field'])) {
					throw new Exception('Field count does not match config', EXCEPTION::QUERY_FIELD_COUNT_INCORRECT);
				}
				if(count($value) == 1) {
					$this->_properties[$name] = current($value);
				}
				else {
					$this->_properties[$name] = $value;
				}
			}
		}
	}		
}
