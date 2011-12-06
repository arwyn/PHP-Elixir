<?php
namespace \Elixir\Filter;

class ModelFieldToProperty implements FilterInterface {
	public function __construct($model_class) {
		$config = $model_class::getConfig();
		$map = array();
		foreach($config['byProperty'] as $property => $spec) {
			$entry = array();
			foreach((array)$spec['field'] as $k => $v) {
				if(is_int($k)) {
					$k = $v;
				}
				$entry[$k] = $v;
			}
			$map[$property] = $entry;
		}
		$this->_map = $map;
	}
	
	public function filter($value) {
		$filtered = array();
		foreach($this->_map as $prop => $fields) {
			$entry = array();
			foreach($fields as $k => $v) {
				if(isset($value[$v])) {
					$entry[$k] = $value[$v];
				}
			}
			if(count($entry) == 1) {
				$filtered[$prop] = current($entry);
			}
			elseif(count($entry)) {
				$filtered[$prop] = $entry;
			}
		}
		return $filtered;
	}
}
