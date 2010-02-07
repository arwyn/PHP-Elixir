<?php

class Elixir_Db_Adapter_Zend implements Elixir_Db_Adapter_Interface {
	protected $_adapter;

	public function __construct(Zend_Db_Adapter_Interface $adapter = null) {
		if(!$adapter) {
			$adapter = Zend_Db_Table::getDefaultAdapter();
		}
		$this->_adapter = $adapter;
	}
	
	public function query() {
		return new Elixir_Db_Query_Zend($this->_adapter);
	}

	public function fetchRow(Elixir_Db_Query_Interface $query) {
		if(!$select instanceof Elixir_Db_Query_Zend) {
			throw Elixir_Exception('you can only use Zend Query objects with this adapter');
		}
		return $this->_adapter->fetchRow($query->getZendSelect(), array(), Zend_Db::FETCH_ASSOC);
	}

	public function fetchAssoc(Elixir_Db_Query_Interface $query) {
		if(!$query instanceof Elixir_Db_Query_Zend) {
			throw Elixir_Exception('you can only use Zend Query objects with this adapter');
		}
		return $this->_adapter->fetchAssoc($query->getZendSelect());
	}
}
