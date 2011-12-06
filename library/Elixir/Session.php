<?php
namespace Elixir;

class Session {
	private static $_registry = array();
	
	public static function get($name = null) {
		if(!$name) {
			$name = 'default';
		}
		if(!isset(static::$_registry[$name])) {
			static::$_registry[$name] = new static();
		}
		return static::$_registry[$name];
	}
	
	public static function set($name, Session $session) {
		static::$_registry[$name] = $session;
		return $this;
	}
	
	public function addActivity($type, $object) {
		$this->_activity[] = array($type, $object);
	}
	
	public function flush() {
		$activities = $this->_cleanDuplicates($this->_activity);
		$this->_activity = array();
		
		foreach($activities as $activity) {
			$method = 'doActivity' . ucfirst($activity[0]);
			$model = $activity[1];
			$this->$method($model);
		}
	}
	
	public function doActivityNew($model) {
		$this->initTransaction();
		$id = $model->getAdapter()->update($model->getChanges(), $model->getId(true));
	}
	
	public function doActivityUpdate($model) {
		$this->initTransaction();
		if($changes = $model->getChanges()) {
			$id = $model->getAdapter()->update($changes, $model->getId(true));
		}
	}
	
	public function doActivityDelete($model) {
		
	}
	
}
