<?php

namespace Test;

use \Elixir\Model as Elixir;

class User extends Elixir {
	private $_elixir_config = array(
		'id' => array('uid'),
		'properties' => array(
			'user' => array('field'=>'uid', 'model'=>'int', 'rel'=>null,'group'=>array()),
			'username' => array('field'=>'username', 'model'=>'string', 'rel'=>null, 'group'=>array('password')),
			'password' => array('field'=>'password', 'model'=>'string', 'rel'=>null, 'group'=>array('username')),
			'favority' => array('field'=>array('fav'=>'pid'), 'model'=>'\Test\Property', 'rel'=>'1','group'=>array()),
			'properties' => array('field'=>array('uid'=>'user'), 'model'=>'\Test\Property', 'rel'=>'m','group'=>array()),
		),
		'adapter' => 'default',
		'table' => 'user',
		'fields' => array(
			'uid' => array('property'=>array('user','properties')),
			'username' => array('property'=>array('username')),
			'password' => array('property'=>array('password')),
			'fav' => array('property'=>array('favority')),
		)
	);
}

class Property extends Elixir {
	private $_elixir_config = array(
		'id' => array('id'),
		'properties' => array(
			'id' => array('id'=>'id', 'model'=>'int', 'rel'=>null,'group'=>array()),
			'value' => array('field'=>'value', 'model'=>'string', 'rel'=>null,'group'=>array()),
			'user' => array('field'=>'user', 'model'=>'\Test\User', 'rel'=>'1','group'=>array()),
		),
		'adapter' => 'default',
		'table' => 'property',
		'fields' => array(
			'id' => array('property'=>array('id')),
			'value' => array('property'=>array('value')),
			'user' => array('property'=>array('user')),
		)
	);
}

