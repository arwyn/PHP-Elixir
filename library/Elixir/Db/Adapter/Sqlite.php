<?php
require_once 'Elixir/Db/Adapter/Interface.php';
require_once 'Elixir/Db/Query/Interface.php';
require_once 'Elixir/Db/Query.php';

class Elixir_Db_Adapter_Sqlite extends Elixir_Db_Adapter_Abstract {
	protected $_db = null;
	
	public function init() {
		if($this->_db) {
			return;
		}
		$this->_db = sqlite_open($this->_params['db_file']);
	}
	
	public function execSelect($query) {
		$this->init();
		$res = sqlite_unbuffered_query($this->_db, $query);
		return sqlite_fetch_all($res, SQLITE_ASSOC);
	}	
	
	public function execUpdate($query) {
		$this->init();
		sqlite_exec($this->_db, $query);
		return sqlite_changes($this->_db);
	}
	
	public function execInsert($query) {
		//todo
	}
	
	public function execDelete($query) {
		//todo
	}
	
}
