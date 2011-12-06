<?php
namespace Test;

use \Elixir\Model as Elixir;


/**
 * User table
 *
 * @property int $user (uid)
 * @property string $username
 * @property string $password
 * @property \Test\Property $favority (fav: pid) Favorite property of this user
 * @property-read \Test\Property $properties (uid: user) Properties of this user
 *
 * @elixirAdapter default [Default: default]
 * @elixirTable user [Default: strtolower(get_class())]
 */
class User extends Elixir {}

/**
 * User table
 *
 * @property int $id 
 * @property string $value
 * @property \Test\User $user (user: uid)
 *
 * @elixirAdapter default [Default: default]
 * @elixirTable property [Default: strtolower(get_class())]
 */
class Property extends Elixir {}
