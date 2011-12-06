<?php
namespace \Elixir\Query;

interface QueryInterface extends \IteratorAggregate {
	public function setOrder(array $order);
	public function setLimit($limit, $page = 0);
	public function setSelect(array $fields);
	public function setFrom($from);
	public function setWhere(array $where);
	
	public function setSession($session);
	public function setAdapter($adapter);
	
	public function setFilter($filter);
	
	public function getResults();
	public function fetch();
}

	
