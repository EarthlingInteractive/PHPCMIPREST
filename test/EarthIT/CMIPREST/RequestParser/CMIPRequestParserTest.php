<?php

class EarthIT_CMIPREST_RequestParser_CMIPRequestParserTest extends PHPUnit_Framework_TestCase
{
	public function testParse() {
		$parser = new EarthIT_CMIPREST_RequestParser_CMIPRequestParser();
		$req = $parser->parse('GET', '/fooples', 'firstName=Ted&lastName=Bundy&limit=100,25&orderBy=-birthDate,%2Bweight,ssn' );
		$this->assertEquals('fooples', $req['collectionName'] );
		$this->assertNull($req['instanceId']);
		$this->assertEquals(array(
			array('fieldName'=>'birthDate','direction'=>'DESC'),
			array('fieldName'=>'weight','direction'=>'ASC'),
			array('fieldName'=>'ssn','direction'=>'ASC')
		), $req['orderBy']);
		$this->assertEquals(100, $req['skip']);
		$this->assertEquals(25, $req['limit']);
		$this->assertEquals(array(
			array('fieldName'=>'firstName','opName'=>'eq','value'=>'Ted'),
			array('fieldName'=>'lastName','opName'=>'eq','value'=>'Bundy')
		), $req['filters']);
	}
}
