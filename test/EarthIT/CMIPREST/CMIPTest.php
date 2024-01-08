<?php

class EarthIT_CMIPREST_CMIPTest extends EarthIT_CMIPREST_TestCase
{
	public function setUp() : void {
		$this->schema = $this->loadTestSchema();
		$this->schemaObjectNamer = EarthIT_CMIPREST_Namers::getStandardCamelCaseNamer();
		$this->storage = new EarthIT_CMIPREST_MemoryStorage();
		$this->rester = new EarthIT_CMIPREST_RESTer(array(
			'storage' => $this->storage,
			'schema' => $this->schema,
			'authorizer' => new EarthIT_CMIPREST_RESTActionAuthorizer_Doormat()
		));
		$this->standardActionContext = new EarthIT_CMIPREST_RESTerTest_Context(1337);
		
		$this->storage->saveItems(array(
			array('ID' => '100', 'URN' => 'data:,Hi'),
			array('ID' => '200', 'URN' => 'data:,Bye'),
		), $this->rc('resource'));
		$this->storage->saveItems(array(
			array('ID' => '300', 'first name' => 'Jack', 'last name' => 'Black'),
			array('ID' => '400', 'first name' => 'Ben', 'last name' => 'Franklin'),
		), $this->rc('person'));
		$this->storage->saveItems(array(
			array('author ID' => '300', 'subject ID' => '100', 'comment' => 'Sux!'),
			array('author ID' => '300', 'subject ID' => '200', 'comment' => 'Just Okay'),
			array('author ID' => '400', 'subject ID' => '100', 'comment' => 'Muchly'),
			array('author ID' => '400', 'subject ID' => '200', 'comment' => 'Thusly'),
		), $this->rc('rating'));
	}
	
	public function testGetItemsGroupedByClass() {
		$parser = new EarthIT_CMIPREST_RequestParser_CMIPRequestParser($this->schema, $this->schemaObjectNamer);
		$req = $parser->parse('GET', '/resources;groupedByClass;with=ratings', '');
		$act = $parser->toAction($req);
		
		$co = $this->rester->doAction($act, $this->standardActionContext);
		$this->assertEquals(array('resources','ratings'), array_keys($co));
		
		$rez = $this->rester->doActionAndGetHttpResponse($act, $this->standardActionContext);
		$this->assertEquals(200, $rez->getStatusCode());
		$co = EarthIT_JSON::decode($rez->getContent());
		$this->assertEquals(array('resources','ratings'), array_keys($co));		
	}
	
	public function testWithWithoutKeyingByIds() {
		$parser = new EarthIT_CMIPREST_RequestParser_CMIPRequestParser($this->schema, $this->schemaObjectNamer);
		$req = $parser->parse('GET', '/resources;with=ratings;keyByIds=false', '');
		$act = $parser->toAction($req);
		
		$resources = $this->rester->doAction($act, $this->standardActionContext);
		$this->assertTrue( is_array($resources) );
		$this->assertTrue( count($resources) > 0 );
		foreach( $resources as $resource ) {
			$this->assertTrue( array_key_exists('ratings',$resource), "Each resource should have 'ratings'" );
			$this->assertTrue( is_array($resource['ratings']) );
		}
	}
}
