<?php

class EarthIT_CMIPREST_RequestParser_CMIPRequestParserTest extends EarthIT_CMIPREST_TestCase
{
	public function setUp() {
		$this->schema = $this->loadTestSchema();
		$this->schemaObjectNamer = EarthIT_CMIPREST_Namers::getStandardCamelCaseNamer();
	}
	
	public function testParseSearch() {
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
			array('fieldName'=>'firstName','pattern'=>'Ted'),
			array('fieldName'=>'lastName','pattern'=>'Bundy')
		), $req['filters']);
	}
	
	public function testParseSearchWithoutIdKeysUsingCollectionModifier() {
		$parser = new EarthIT_CMIPREST_RequestParser_CMIPRequestParser($this->schema, $this->schemaObjectNamer);
		$req = $parser->parse('GET', '/people;keyByIds=false', 'firstName=Ted&lastName=Bundy&limit=100,25' );
		$this->assertEquals('people', $req['collectionName'] );
		$this->assertNull($req['instanceId']);
		$this->assertEquals('false', $req['collectionModifiers']['keyByIds']);
		$act = $parser->toAction($req);
		$this->assertFalse($act->getResultAssembler()->keyByIds);
	}
	
	public function testParseSearchWithoutIdKeysUsingGeneralModifier() {
		$parser = new EarthIT_CMIPREST_RequestParser_CMIPRequestParser($this->schema, $this->schemaObjectNamer);
		$req = $parser->parse('GET', ';keyByIds=false/people', 'firstName=Ted&lastName=Bundy&limit=100,25' );
		$this->assertEquals('people', $req['collectionName'] );
		$this->assertNull($req['instanceId']);
		$this->assertEquals('false', $req['generalModifiers']['keyByIds']);
		$act = $parser->toAction($req);
		$this->assertFalse($act->getResultAssembler()->keyByIds);
	}
	
	public function testToAction() {
		$parser = new EarthIT_CMIPREST_RequestParser_CMIPRequestParser($this->schema, $this->schemaObjectNamer);
		$req = $parser->parse('GET', '/people', 'firstName=Ted&lastName=Bundy&limit=100,25&orderBy=-id' );
		
		$act = $parser->toAction($req);
		$this->assertTrue( $act instanceof EarthIT_CMIPREST_RESTAction_SearchAction );
		$this->assertTrue( $act->getResultAssembler()->keyByIds );
		$search = $act->getSearch();
		$this->assertEquals( 'EarthIT_Storage_Filter_AndedItemFilter', get_class($search->getFilter()) );
		$this->assertEquals( 100, $search->getSkip() );
		$this->assertEquals( 25, $search->getLimit() );
		$ob = $search->getComparator()->getComponents();
		$this->assertEquals( 1, count($ob) );
	}
	
	public function testMultiPostToAction() {
		$parser = new EarthIT_CMIPREST_RequestParser_CMIPRequestParser($this->schema, $this->schemaObjectNamer);
		$req = $parser->parse('POST', '/people', '', new EarthIT_JSON_PrettyPrintedJSONBlob(array(
			array('firstName'=>'Bob','lastName'=>'Lindmeyer'),
			array('firstName'=>'Bob','lastName'=>'Saget')
		)));
		$act = $parser->toAction($req);
	}
	
	public function testParseInvalid() {
		$parser = new EarthIT_CMIPREST_RequestParser_CMIPRequestParser($this->schema, $this->schemaObjectNamer);
		$caught = null;
		try {
			$req = $parser->parse('POST', '/people/12', '', new EarthIT_JSON_PrettyPrintedJSONBlob(array(
				array('firstName'=>'Bob','lastName'=>'Lindmeyer'),
				array('firstName'=>'Bob','lastName'=>'Saget')
			)));
			$act = $parser->toAction($req);
		} catch( EarthIT_CMIPREST_RequestInvalid $e ) {
			$caught = $e;
		}
		$this->assertNotNull($caught);
		$this->assertTrue( is_array($caught->getErrorDetails()) );
	}
}
