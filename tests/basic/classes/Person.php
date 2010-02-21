<?php
require_once 'Elixir/Object.php';
require_once 'Elixir/Db/Adapter/Pdo.php';

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
			'type' => 'date',
		),
		'married' => array(
			'type' => 'boolean'
		),
		'height' => array(
			'type' => 'float'
		)
	);
	
	protected static $_adapter = array(
		'class' => 'Elixir_Db_Adapter_Pdo',
		'dsn' => 'sqlite:basic.db'
	); 
}