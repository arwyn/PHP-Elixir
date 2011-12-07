<?php
require_once 'Elixir/Utils.php';

class Elixir_Session {
	static private $_registry = null;
	static private $_default = null;
	
	protected $_objects = array();
	protected $_changes = array();
	protected $_index = array();
	protected $_status = array();
	
	protected $_oid = null;
	
	public function __construct() {
		$this->_oid = Elixir_Utils::UUIDv4();
		self::_registerSession($this);
	}
	
	static public function getDefaultSession() {
		if(!self::$_default) {
			$session = new self();
			self::$_default = $session->getOID();
		}
		return self::getSession(self::$_default);
	}
	
	static public function setDefaultSession(Elixir_Session $session) {
		$id = $session->getOID();
		if(!isset(self::$_registry[$id])) {
			self::_registerSession($session);
		}
		self::$_default = $id;
	}
	
	static public function getSession($session_id) {
		return isset(self::$_registry[$session_id]) ? self::$_registry[$session_id] : null;
	}
	
	static protected function _registerSession(Elixir_Session $session) {
		$id = $session->getOID();
		if(isset(self::$_registry[$id])) {
			require_once 'Elixir/Exception.php';
			throw new Elixir_Exception('session already registered');
		}
		self::$_registry[$id] = $session;
	}
	
	public function setStatus($elixir, $details = null) {
		$hash = $elixir->getOID();
		
		// if not in session, add.
		if(!isset($this->_objects[$hash])) {
			$this->_objects[$hash] = $elixir;
		}
		// if we can index id, do so
		if($id = $elixir->getId()) {
			$this->_index[serialize($id)] = $hash;
		}
		// index the change that was made.
		if($details) {
			$class = get_class($elixir);
			if(!isset($this->_changes[$class])) {
				$this->_changes[$class] = array();
			}
			foreach((array)$details as $field) {
				if(!isset($this->_changes[$class][$field])) {
					$this->_changes[$class][$field] = array();
				}
				$this->_changes[$class][$field][$hash] = true;
			}
		}
		// index status
		$_status[$hash] = $elixir->getStatus();
	}
	
	public function get($id) {
		return isset($this->_index[$id]) ? $this->_objects[$this->_index[$id]] : false;
	}
	
	public function getOID() {
		return $this->_oid;
	}
}