<?php
require_once 'Elixir/Adapter.php';
require_once 'Elixir/Constraint.php';

class Elixir_Adapter_Pdo extends Elixir_Adapter {
	protected $_db = null;
	
	public function init() {
		if($this->_db) {
			return;
		}
		$this->_db = new PDO($this->_params['dsn']);
	}
	
	protected function _execSelect($query, $bind) {
		$this->init();
		if(($stmt = $this->_db->prepare($query)) && $stmt->execute($bind)) {
			$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
			$stmt->closeCursor();
			return $rows;
		}
		require_once 'Elixir/Exception/Adapter.php';
		throw new Elixir_Exception_Adapter('invalid query: '. $query);
	}	
	
	protected function _execUpdate($query, $bind) {
		$this->init();
//		sqlite_exec($this->_db, $query);
//		return sqlite_changes($this->_db);
	}
	
	protected function _execInsert($query, $bind) {
		//todo
	}
	
	protected function _execDelete($query, $bind) {
		//todo
	}
}
