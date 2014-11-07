<?php

class EarthIT_CMIPREST_RESTerTest extends PHPUnit_Framework_TestCase
{
	protected $dbAdapter;
	protected $storage;
	protected $rester;
	protected $schema;
	
	protected $savedItems;
	
	public function setUp() {
		// Relative to the pwd, yes.
		$dbConfigFile = 'config/test-dbc.json';
		$dbConfigJson = file_get_contents($dbConfigFile);
		if( $dbConfigJson === null ) {
			throw new Exception("Failed to load database config from $dbConfigFile");
		}
		$dbConfig = EarthIT_JSON::decode($dbConfigJson);
		$this->dbAdapter = Doctrine\DBAL\DriverManager::getConnection($dbConfig);
		$this->schema = require 'test-schema.php';
		$this->storage = new EarthIT_CMIPREST_PostgresStorage($this->dbAdapter, $this->schema, new EarthIT_DBC_PostgresNamer());
		$this->rester = new EarthIT_CMIPREST_RESTer(array(
			'storage' => $this->storage,
			'schema' => $this->schema
		));
		
		$rc = $this->schema->getResourceClass('resource');
		$this->savedItems = array(
			$this->storage->postItem( $rc, array('URN'=>'data:text/plain,'.rand(1000000,9999999))),
			$this->storage->postItem( $rc, array('URN'=>'data:text/plain,'.rand(1000000,9999999)))
		);
	}
	
	protected function getItem( $rc, $id ) {
		return EarthIT_CMIPREST_Util::getItemById($this->storage, $rc, $id);
	}
	
	public function testGetItem() {
		$rc = $this->schema->getResourceClass('resource');
		
		foreach( $this->savedItems as $savedItem ) {
			$getItemResult = $this->rester->doAction( new EarthIT_CMIPREST_UserAction_GetItemAction(0, $rc, $savedItem['ID'], array() ) );
			$this->assertEquals($savedItem['ID'], $getItemResult['id']);
			$this->assertEquals($savedItem['URN'], $getItemResult['urn']);
		}
	}
	
	public function testPostItems() {
		$rc = $this->schema->getResourceClass('resource');
		
		$items = array();
		for( $i=0; $i<5; ++$i ) {
			$items[] = array('URN'=>'data:text/plain,'.rand(1000000,9999999));
		}
		$posted = $this->rester->doAction( new EarthIT_CMIPREST_UserAction_PostItemsAction(0, $rc, $items) );
		for( $i=0; $i<5; ++$i ) {
			$this->assertNotNull($posted[$i]['id']);
			$this->assertEquals($items[$i]['URN'], $posted[$i]['urn']);
		}
	}
	
	public function testPostItem() {
		$rc = $this->schema->getResourceClass('resource');
		
		$urn = 'data:text/plain,'.rand(1000000,9999999);
		$posted = $this->rester->doAction( new EarthIT_CMIPREST_UserAction_PostItemAction(0, $rc, array('URN'=>$urn)) );
		$got = $this->getItem($rc, $posted['id']);
		$this->assertEquals($urn, $got['URN']);
	}
	
	public function testDeleteItem() {
		$rc = $this->schema->getResourceClass('resource');
		$urn = 'data:text/plain,'.rand(1000000,9999999);
		$posted = $this->storage->postItem( $rc, array('URN'=>$urn) );
		$id = $posted['ID'];
		$got = $this->getItem($rc, $id);
		$this->assertEquals($urn, $got['URN']);
		
		// Now baleete it!
		$this->rester->doAction( new EarthIT_CMIPREST_UserAction_DeleteItemAction(0, $rc, $id) );
		
		// Now make sure it's gone!
		$this->assertNull( $this->getItem($rc, $id) );
	}
}
