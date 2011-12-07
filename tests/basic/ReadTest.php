<?php
require_once 'PHPUnit/Framework.php';
require_once 'classes/Person.php';

class ReadTest extends PHPUnit_Framework_TestCase {
	public function setup() {
		date_default_timezone_set('Asia/Tokyo');
	}

	public function testBuiltinTypes() {
		$person = Person::get(1);
		$this->assertTrue($person instanceof Elixir_Type, 'must be instance of Elixir_Type');
		$this->assertTrue($person instanceof Person, 'must be instance of Person');

		$this->assertType('string', $person->first_name, 'First name must be string');
		$this->assertEquals('John', $person->first_name, 'First name should be John');
		$this->assertType('string', $person->surname, 'Surname must be string');
		$this->assertEquals('Smith', $person->surname, 'Surname should be Smith');

		$this->assertType('int', $person->id, 'Id must be int');
		$this->assertEquals(1, $person->id, 'Id expected to be 1');

		$this->assertType('float', $person->height, 'Height must be float');
		$this->assertEquals(1.75, $person->height, 'Height should be 1.75');

		$this->assertType('boolean', $person->married, 'Married must be boolean');
		$this->assertEquals(true, $person->married, 'Married should be true');

		$this->assertType('object', $person->dob, 'Date of birth must be object');
		$this->assertTrue($person->dob instanceof DateTime, 'Date of birth must be instance of DateTime');
		$this->assertEquals('1982-05-27', $person->dob->format('Y-m-d'), 'Date of Birth should 1982-05-27');
	}
	
	public function testGetArray() {
		$people = Person::getBy();
		
		$this->assertTrue($people instanceof Elixir_Array, 'must be instance of Elixir_Array');
		$this->assertEquals(2, count($people), 'must contain 2 person classes');
		$this->assertEquals('Person', $people->getType(), 'must contain person classes');
		
		foreach($people as $person) {
			$this->assertTrue($person instanceof Person, 'must be instance of Person');
			$this->assertType('int', $person->id, 'Id must be int');
		}
	}
	
	public function testOneToOneRelation() {
		$person = Person::get(1);
		
		$partner = $person->partner;
		$this->assertTrue($partner instanceof Person, 'must be a Person instance');
		$this->assertEquals(2, $partner->id, 'partner id');
	}
}