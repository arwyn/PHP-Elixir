<?php
namespace \Elixir\Adapter;

interface AdapterInterface {
	public function query($from, array $select = null, array $where = null, array $order = null, array $limit = null, array $params = null);
	public function beginTransaction();
	public function commit();
	public function rollback();
}
