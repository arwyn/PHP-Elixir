<?php
require_once 'Elixir/Exception.php';

class Elixir_Exception_Adapter extends Elixir_Exception {
	protected $message = 'adapter error';
}