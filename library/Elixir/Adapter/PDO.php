<?php
namespace \Elixir\Adapter;

class PDO extends SQL {
	protected $_options = null;
	protected $_db = null;
	
	public function __construct($options) {
		$this->_options = $options;
	}
	
	public function query($from, array $select = null, array $where = null, array $order = null, array $limit = null, array $params = null) {
		$sql = $this->_generateQuery($from, $select, $where, $order, $limit);
		$db = $this->getDb();
		$statement = $db->prepare($sql[0]);
		$statement->execute(array_merge($sql[1], (array)$params));
		return $statement->fetchAll(\PDO::FETCH_ASSOC);
	} 
	
	protected function getDb() {
		if(!$this->_db) {
			$this->_db = new \PDO($this->_options['dsn'], $this->_options['username'], $this->_options['password'], $this->_options['options']);
		}
		return $this->_db;
	}
	
	public function beginTransaction() {
		$this->getDb()->beginTransaction();
	}
	
	public function commit() {
		$this->getDb()->commit();
	}
	
	public function rollback() {
		$this->getDb()->rollback();
	}
}
