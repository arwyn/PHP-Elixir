<?php

interface Elixir_Db_Adapter_Interface {
	public function fetchRow(Elixir_Db_Select_Interface $select);
	public function fetchAssoc(Elixir_Db_Select_Interface $select);
}