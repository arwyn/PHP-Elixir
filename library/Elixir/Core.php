<?php
namespace Elixir;

class Core {
	public static function getIdFromFields($class, $properties) {
		$config = $class::getConfig();
		
		$id = array();
		foreach($config['id'] as $k => $v) {
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
	
	public static function getFieldsFromProperty($class, $name, $value) {
	
	}
	
	public static function assertFieldIsValid($class, $field) {}
}
