<?php

class EarthIT_CMIPREST_RESTerTest extends PHPUnit_Framework_TestCase
{
	public function setUp() {
		echo "Hi!";
	}
	
	public function testAbc() {
		new EarthIT_CMIPREST_RESTer(array());
		$this->fail("Nothing workx");
	}
}
