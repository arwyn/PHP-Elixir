<?php 

interface Elixir_Db_Query_Interface {
	public function from($table, $cols);
	public function where($column, $var = null, $bind = true);
	
	public function fetchRow();
	public function fetchAssoc();
}