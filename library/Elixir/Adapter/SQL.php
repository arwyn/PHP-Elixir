<?php
namespace \Elixir\Adapter;

abstract class SQL implements AdapterInterface {
	protected function _generateQuery($from, array $select, array $where = null, array $order = null, array $limit = null) {
		$query = '';
		$bind = array();
		
		$select = (array)$select;
		foreach($select as &$entry) {
			$entry = '`' . $entry . '`';
		}
		$query .= 'SELECT ' . implode(', ', $select);
		
		$query .= ' FROM ' . $from;
		
		$where_sql = array();
		foreach($where as $field => $value) {
			if(is_array($value)) {
				$in = array();
				foreach(array_values($value) as $k => $v) {
					$bind[$in[] = ':' . $field . $k] = $v; 
				}
				if($in) {
					$where_sql[] = $field . ' IN (' . implode(',', $in) . ')';
				}
			}
			else {
				$where_sql[] = $field . '=:' . $field . '0';
				$bind[$field . '0'] = $value;
			}
		}
		$query .= ' WHERE ' . implode(' && ', $where_sql);
		
		$order_sql = array();
		foreach((array)$order as $spec) {
			if(!is_array($spec)) {
				$spec = array($spec, 'ASC');
			}
			$order_sql[] = $spec[0] . ' ' . $spec[1];
		}
		if($order_sql) {
			$query .= ' ORDER BY ' . implode(', ', $order_sql);
		}
		
		if($limit) {
			$query .= ' LIMIT ' . $limit[0] . ', ' . $limit[1];
		}
		
		return array($query, $bind);
	}
}
