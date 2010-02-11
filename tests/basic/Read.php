<?php
require_once 'PHPUnit/Framework.php';
require_once 'classes/Person.php';
require_once 'Elixir/Db/Adapter.php';

class ReadTest extends PHPUnit_Framework_TestCase {
	public $person = null;
	
	public function testGet() {
		
		$this->person = Person::get(1);
		$this->assertTrue($this->person instanceof Elixir_Object, 'must be instance of Elixir_Object');
		$this->assertTrue($this->person instanceof Person, 'must be instance of Person');
	}
	
	/**
	 * 
	 * @depends testGet
	 */
	public function testString() {
		$this->assertType('string', $this->person->first_name, 'First name must be string');
		$this->assertEquals('John', $this->person->first_name, 'First name should be John');
		$this->assertType('string', $this->person->surname, 'Surname must be string');
		$this->assertEquals('Smith', $this->person->surname, 'Surname should be Smith');
	}
	
	/**
	 * 
	 * @depends testGet
	 */
	public function testInt() {
		$this->assertType('int', $this->person->id, 'Id must be int');
		$this->assertEquals(1, $this->person->id, 'Id expected to be 1');
	}
	
	/**
	 * 
	 * @depends testGet
	 */
	public function testFloat() {
		$this->assertType('float', $this->person->height, 'Height must be float');
		$this->assertEquals(1.75, $this->person->height, 'Height should be 1.75');
	}
	
	/**
	 * 
	 * @depends testGet
	 */
	public function testBoolean() {
		$this->assertType('boolean', $this->person->married, 'Married must be boolean');
		$this->assertEquals(true, $this->person->married, 'Married should be true');
	}
	
	/**
	 * 
	 * @depends testGet
	 */
	public function testDatetime() {
		$this->assertType('object', $this->person->dob, 'Date of birth must be object');
		$this->assertTrue($this->person->dob instanceof DateTime, 'Date of birth must be instance of DateTime');
		$this->assertEquals('1982-05-27', $this->person->dob->format('Y-m-d'), 'Date of Birth should 1982-05-27');
	}
	
	
	
};