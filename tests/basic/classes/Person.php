<?php
require_once 'Elixir/Object.php';
require_once 'Zend/Db.php';
require_once 'Elixir/Db/Adapter/Zend.php';

class Person extends Elixir_Object {
	protected static $_table = 'person';
	protected static $_definition = array(
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
		static::$_adapter = new Elixir_Db_Adapter_Zend(Zend_Db::factory('Pdo_Sqlite',array(
			'dbname' => dirname(__FILE__ . '/../basic.db')
		)));
	}
}