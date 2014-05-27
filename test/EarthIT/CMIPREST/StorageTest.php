<?php

abstract class EarthIT_CMIPREST_StorageTest extends PHPUnit_Framework_TestCase
{
	protected $storage;
	protected $schema;
	
	protected abstract function createStorage();
	
	public function setUp() {
		$this->schema = require 'test-schema.php';
		$this->storage = $this->createStorage();
	}
	
	public function testStoragePost() {
		$resourceRc = $this->schema->getResourceClass('resource');
		$urn = 'data:text/plain,'.rand(1000000,9999999);
		$item = $this->storage->postItem( $resourceRc, array('URN' => $urn ) );
		$this->assertNotNull($item);
		$this->assertEquals($urn, $item['URN']);
		$this->assertNotNull($item['ID']);
		
		$refetchedItem = $this->storage->getItem($resourceRc, $item['ID']);
		$this->assertEquals( $item, $refetchedItem );
	}
}
