<?php

class EarthIT_CMIPREST_RequestParser_CMIPRequestParserTest extends EarthIT_CMIPREST_TestCase
{
	public function setUp() {
		$this->schema = $this->loadTestSchema();
		$this->schemaObjectNamer = function($obj,$plural=false) {
			$name = $plural ?
				($rc->getFirstPropertyValue(EarthIT_CMIPREST_NS::COLLECTION_NAME) ?:
				 EarthIT_Schema_WordUtil::pluralize($obj->getName())) :
				$obj->getName();
			return EarthIT_Schema_WordUtil::toCamelCase($name);
		};
	}
	
	public function testParse() {
		$parser = new EarthIT_CMIPREST_RequestParser_CMIPRequestParser($this->schema, $this->schemaObjectNamer);
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
			array('fieldName'=>'firstName','opName'=>'eq','pattern'=>'Ted'),
			array('fieldName'=>'lastName','opName'=>'eq','pattern'=>'Bundy')
		), $req['filters']);
	}
	
	public function testToAction() {
		$parser = new EarthIT_CMIPREST_RequestParser_CMIPRequestParser($this->schema, $this->schemaObjectNamer);
		$req = $parser->parse('GET', '/people', 'firstName=Ted&lastName=Bundy&limit=100,25&orderBy=-id' );
		
		$act = $parser->toAction($req);
		$this->assertTrue( $act instanceof EarthIT_CMIPREST_RESTAction_SearchAction );
		$sp = $act->getSearchParameters();
		$this->assertEquals( 2, count($sp->getFieldMatchers()) );
		$this->assertEquals( 100, $sp->getSkip() );
		$this->assertEquals( 25, $sp->getLimit() );
		$ob = $sp->getOrderByComponents();
		$this->assertEquals( 1, count($ob) );

	}
}
