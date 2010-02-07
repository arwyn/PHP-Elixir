<?php
class Elixir_Exception_DuplicateRow extends Elixir_Exception {
	protected $message = 'duplicate row. row with given primary keys already exists';
}