<?php
namespace Elixir;

use Exception\Model as Exception;

class Model {
	const LAZYLOAD = 1;
	
	private static abstract $_elixir_config = null;

	protected $_elixir_container = null;

	public function __construct($data = null, $options = 0, $session = null) {
		static::_init();
		
		$this->_elixir_container['session'] = $session;
		
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
		if(!isset(static::$_elixir_config['properties'][$name])) {
			throw new Exception('Invalid Property', Exception::INVALID_PROPERY);
		}
		
		$model_class = static::$_elixir_config['properties'][$name]['model'];
		if($model_class && !in_array($model_class, array('int','float','double','bool','boolean','string'))) {
			if($value instanceof $model_class) {
				$value = $value->getId(true);
			}
			else {
				$value = Core::getIdFromFields($model_class, $value);
			}
		}
		else {
			$value = Core::getFieldsFromProperty($model_class, $name, $value);
		}
		
		foreach($value as $field => $val) {
			if(!isset($this->_elixir_container['fields'][$field]) || $this->_elixir_container['fields'][$field] != $val)  {
				$this->_elixir_container['fields'][$field] = $value;
				$this->_elixir_container['changed'][$field] = true;
				$this->getSession()->addActivity('update', $this);
			}
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
			throw new Exception('Id not given', Exception::QUERY_FIELD_COUNT_INCORRECT);
		}

		if(!is_array($data['id'])) {
			$data['id'] = array($data['id']);
		}
	
		$model_class = get_called_class();
		$data['id'] = Core::getIdFromFields($model_class, $data['id']);
	
		$fields = array();
		foreach($data['properties'] as $name => $value) {
			$fields = array_merge($fields, Core::getFieldsFromProperties($model_class, $name, $value));
		}
		unset($data['properties']);
		foreach($data['fields'] as $name => $value) {
			Core::assertFieldIsValid($model_class, $name);
			$fields[$name] = $value;
		}
		$data['fields'] = $fields;

		$model = unserialize('O:'.count($model_class).'"'.$model_class.'":0:{}');
	
		$model->_elixir_container = $data;

		return $model;
	}
	
	public function getId($full = false) {
		if(!$full && $this->_elixir_container['id'] && count($this->_elixir_container['id']) == 1) {
			return current($this->_elixir_container['id']);
		}
		return $this->_elixir_container['id'];
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
				if(static::$_elixir_config['properties'][$name]['ref'] == 'm') {
					$property = $name::get($query, $this->_elixir_container['session'], static::LAZYLOAD);
				}
				else {
					$property = $name::query($query, $this->_elixir_container['session']);
				}
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

		if(!is_array($query)) {
			$query = array('id'=>$query);
		}
		if(!is_array($query['id'])) {
			$query['id'] = array($query['id']);
		}
		
		$model_class = get_called_class();
		
		if(isset($query['id']) && $options & static::LAZYLOAD) {
			$id = Core::getIdFromFields($model_class, $query['id']);			
			unset($query['id']);
			$model = $model_class::__get_state(array('id' => $id, 'properties' => $query, 'session' => $session));;
		}
		else {
			$model = null;
			$result = $model_class::query($query, $session)->setLimit(2,0);
			$i = 0;
			foreach($result as $model) {
				$i++;
			}
			if($i > 1) {
				throw new Exception('too many results returned for a unique value', Exception::TOO_MANY_RESULTS);
			}
		}
		
		return $model;
	}
	
	public static function query($query, $session = null, $options = 0) {
		static::_init();
	}
	
	public static function getConfig() {
		if(!static::$_elixir_config) {
			static::$_elixir_config = Core::extractConfigFromClass(get_called_class());
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
