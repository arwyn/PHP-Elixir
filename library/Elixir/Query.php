<?php
namespace \Elixir;

class Query implements Query\QueryInterface {
	public function __construct($session = null) {
		$this->setSession($session);
	}

	public function getSession() {
		return $this->_session;
	}
	
	public function setSession($session) {
		$this->_session = $session;
		return $this;
	}

	public function getSelect() {
		return $this->_select;
	}
	
	public function setSelect(array $fields) {
		$this->_select = $fields;
		$this->reset();
		return $this;
	}
	
	public function getFrom() {
		return $this->_from;
	}
	
	public function setFrom($from) {
		$this->_from = $from;
		$this->reset();
		return $this;
	}
	
	public function getOrder() {
		return $this->_order;
	}
	
	public function setOrder(array $order) {
		$this->_order = $order;
		$this->reset();
		return $this;
	}
	
	public function getLimit() {
		return array($this->_limit, $this->_page);
	}
	
	public function setLimit($limit, $page = 0) {
		$this->_limit = $limit;
		$this->_page = $page;
		return $this;
	}
	
	public function getWhere() {
		return $this->_where;
	}
	
	public function setWhere(array $where) {
		$this->_where = $where;
		$this->reset();
		return $this;
	}
	
	public function addWhere(array $where) {
		$this->_where = array_merge($this->_where, $where);
		$this->reset();
		return $this;
	}
	
	public function getAdapter() {
		return $this->_adapter;
	}
	
	public function setAdapter($adapter) {
		$this->_adapter = $adapter;
		$this->reset();
		return null;
	}
	
	public function getParams() {
		return $this->_params;
	}
	
	public function setParams(array $params) {
		$this->_params = $params;
		$this->reset();
		return $this;
	}
	
	public function addParam($param, $value) {
		$this->_params[$param] = $value;
		return $this;
	}
	
	public function fetch() {
		return Session::get($this->_session)->getAdapter($this->_adapter)->query(
			$this->getFrom(),
			$this->getSelect(),
			$this->getWhere(),
			$this->getOrder(),
			$this->getLimit(),
			$this->getParams()
		);
	}
	
	public function reset() {
		$this->_results = null;
		return $this;
	}

	public function getResults() {
		if(!$this->_results) {
			$this->_results = new Query\Result($this->fetch(), $this->getFilter());
		}
		return $this->_results;
	}
	
	public function getFilter() {
		return $this->_filter;
	}
	
	public function setFilter($filter) {
		$this->_filter = $filter;
	}

	public function getIterator() {
		return $this->getResults();
	}
}
