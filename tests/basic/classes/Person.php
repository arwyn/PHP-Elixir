<?php
require_once 'Elixir/Type.php';
require_once 'Elixir/Adapter/Pdo.php';

class Person extends Elixir_Type {
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
		),
		'partner' => array(
			'type' => 'Person',
			'relation' => self::RELATION_ONE_TO_ONE,
			'field' => array(
				'partner_id' => array('id')
			)
		)
	);
	
	protected static $_adapter = array(
		'class' => 'Elixir_Adapter_Pdo',
		'dsn' => 'sqlite:basic.db'
	); 
}