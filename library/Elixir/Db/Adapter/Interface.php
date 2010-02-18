<?php

interface Elixir_Db_Adapter_Interface {
	public function init();

	public function execSelect($query);
	public function execUpdate($query);
	public function execDelete($query);
	public function execInsert($query);
}
