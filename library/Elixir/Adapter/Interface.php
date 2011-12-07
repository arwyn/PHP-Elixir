<?php

interface Elixir_Adapter_Interface {
	public function init();

	public function select($type, array $field_names, Elixir_Constraint $constraint);
	public function update($type, array $fields, Elixir_Constraint $constraint);
	public function delete($type, Elixir_Constraint $constraint);
	public function insert($type, array $fields);
	public function count($type, Elixir_Constraint $constraint);
	
	public function normalise($var);
}
