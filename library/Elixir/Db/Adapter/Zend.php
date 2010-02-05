<?php

class Elixir_Db_Adapter_Zend implements Elixir_Db_Adapter_Interface {
	protected $_adapter;

	public function __construct(Zend_Db_Adapter_Interface $adapter = null) {
		if(!$adapter) {
			$adapter = Zend_Db_Table::getDefaultAdapter();
		}
		$this->_adapter = $adapter;
	}

	public function fetchRow(Elixir_Db_Select Interface $select) {
		if(!$select instanceof Elixir_Db_Select_Zend) {
			$select = Elixir_Db_Select_Zend::convertToZend($select);
		}
		return $this->_adapter->fetchRow($select->toZendSelect(), array(), Zend_Db::FETCH_ASSOC);
	}

	public function fetchAssoc(Elixir_Db_Select Interface $select) {
		if(!$select instanceof Elixir_Db_Select_Zend) {
			$select = Elixir_Db_Select_Zend::convertToZend($select);
		}
		return $this->_adapter->fetchAssoc($select->toZendSelect());
	}
}
