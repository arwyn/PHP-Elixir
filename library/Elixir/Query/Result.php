<?php
namespace Elixir\Query;

class Result implements \Iterator {
	private $_content = null;
	private $_pos = 0;
	
	public function __construct($content, $filter = null) {
		$this->_content = $content;
		$this->_filter = $filter;
	}
	
	public function rewind() {
		$this->_pos = 0;
	}
	
	public function current() {
		return $this->valid() ? $this->_filter($this->_content[$this->_pos]) : null;
	}
	
	public function next() {
		return $this->_pos++;
	}
	
	public function key() {
		return $this->_pos;
	}
	
	public function valid() {
		return isset($this->_content[$this->_pos]);
	}
	
	private function _filter($value) {
		if($this->_filter && is_callable($this->_filter)) {
			$value = call_user_func($this->_filter, $value);
		}
		return $value;
	}
}
