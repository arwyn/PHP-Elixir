<?php
namespace Elixir;

class Exception extends \Exception {
	const CONFIG_INCOMPLETE = 1;
	const CONFIG_MISSING = 2;
	const QUERY_NOT_UNIQUE = 3;
	const QUERY_FIELD_COUNT_INCORRECT = 4;
}

