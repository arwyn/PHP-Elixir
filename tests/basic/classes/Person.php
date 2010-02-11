<?php
require_once 'Elixir/Object.php';

class Person extends Elixir_Object {
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
}