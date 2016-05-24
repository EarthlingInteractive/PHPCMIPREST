<?php

class EarthIT_PHPCMIPREST_RESTItemCodecTest extends EarthIT_CMIPREST_TestCase {
	protected $restData = array(
		array(
			'id' => 1,
			'firstName' => 'Fred',
			'lastName' => 'Rogers',
		),
		array(
			'firstName' => 'Bob',
			'lastName'=> 'Jones',
		),
	);
	protected $schemaData = array(
		array(
			'ID' => 1,
			'first name' => 'Fred',
			'last name' => 'Rogers',
		),
		array(
			'first name' => 'Bob',
			'last name'=> 'Jones',
		),
	);
	
	protected $codec;
	
	public function setUp() {
		$this->codec = EarthIT_CMIPREST_RESTItemCodec::getInstance();
	}
	
	public function testEncodePeople() {
		$this->assertEquals( $this->restData, $this->codec->encodeItems($this->schemaData, $this->rc('person')) );
	}
	public function testDecodePeople() {
		$this->assertEquals( $this->schemaData, $this->codec->decodeItems($this->restData, $this->rc('person')) );
	}
}