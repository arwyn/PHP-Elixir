<?php
require_once 'Elixir/Db/Adapter.php';
require_once 'Elixir/Db/Constraint.php';

class Elixir_Db_Adapter_Pdo extends Elixir_Db_Adapter {
	protected $_db = null;
	
	public function init() {
		if($this->_db) {
			return;
		}
		$this->_db = new PDO($this->_params['dsn']);
	}
	
	public function execSelect($query) {
		$this->init();
		if($res = $this->_db->query($query)) {
			return $res->fetchAll(PDO::FETCH_ASSOC);
		}
		require_once 'Elixir/Exception/Adapter.php';
		throw new Elixir_Exception_Adapter('invalid query: '. $query);
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
	
	public function quote($var) {
		$this->init();
		if($var instanceof Datetime) {
			$var = $var->format('Y-m-d H:i:s');
		}
		return $this->_db->quote($var);
	}
}
