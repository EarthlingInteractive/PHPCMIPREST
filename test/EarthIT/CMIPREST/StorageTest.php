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
	
	public function testPost() {
		$resourceRc = $this->schema->getResourceClass('resource');
		$urn = 'data:text/plain,'.rand(1000000,9999999);
		$item = $this->storage->postItem( $resourceRc, array('URN' => $urn ) );
		$this->assertNotNull($item);
		$this->assertEquals($urn, $item['URN']);
		$this->assertNotNull($item['ID']);
		
		$refetchedItem = $this->storage->getItem($resourceRc, $item['ID']);
		$this->assertEquals( $item, $refetchedItem );
	}
	
	public function testPatch() {
		$resourceRc = $this->schema->getResourceClass('resource');
		$urn0 = 'data:text/plain,'.rand(1000000,9999999);
		$item0 = $this->storage->postItem( $resourceRc, array('URN' => $urn0) );
		$urn1 = 'data:text/plain,'.rand(1000000,9999999);
		$item1 = $this->storage->putItem( $resourceRc, $item0['ID'], array('URN' => $urn1) );
		
		$this->assertEquals( $urn1, $item1['URN'] );
		
		$refetchedItem = $this->storage->getItem($resourceRc, $item1['ID']);
		$this->assertEquals( $item1, $refetchedItem );
	}
	
	public function testPatchNew() {
		$resourceRc = $this->schema->getResourceClass('resource');
		$urn0 = 'data:text/plain,'.rand(1000000,9999999);
		$item0 = $this->storage->postItem( $resourceRc, array('URN' => $urn0) );
		$urn1 = 'data:text/plain,'.rand(1000000,9999999);
		$item1 = $this->storage->putItem( $resourceRc, -$item0['ID'], array('URN' => $urn1) );
		
		$this->assertEquals( -$item0['ID'], $item1['ID'] );
		$this->assertEquals( $urn1, $item1['URN'] );
		
		$refetchedItem = $this->storage->getItem($resourceRc, $item1['ID']);
		$this->assertEquals( $item1, $refetchedItem );
	}
}
