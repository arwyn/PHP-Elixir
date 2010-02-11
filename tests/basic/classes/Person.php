<?php
require_once 'Elixir/Object.php';
require_once 'Zend/Db.php';

class Person extends Elixir_Object {
	protected $_table = 'person';
	protected $_definition = array(
		'id' => array(
			'field' => 'person_id',
			'type' => 'int',
			'primary_key' => true,
			'auto_increment' => true,
		),
		'first_name' => array(
			'type' => 'string'
		),
		'surname' => array(
			'type' => 'string'
		),
		'dob' => array(
			'type' => 'datetime',
		),
		'married' => array(
			'type' => 'boolean'
		),
		'height' => array(
			'type' => 'float'
		)
	);
	
	static protected function _initAdapter() {
		$this->_adapter = Zend_Db::factory('Zend_Db_Adapter_Pdo_Sqlite',array(
			'dbname' => dirname(__FILE__ . '/../basic.db')
		));
	}
}