<?php

abstract class EarthIT_CMIPREST_StorageTest extends EarthIT_CMIPREST_TestCase
{
	protected $storage;
	protected $schema;
	
	protected abstract function createStorage();
	
	public function setUp() {
		$this->schema = $this->loadTestSchema();
		$this->storage = $this->createStorage();
	}
	
	public function testPost() {
		$resourceRc = $this->schema->getResourceClass('resource');
		$urn = 'data:text/plain,'.rand(1000000,9999999);
		$item = $this->storage->postItem( $resourceRc, array('URN' => $urn ) );
		$this->assertNotNull($item);
		$this->assertEquals($urn, $item['URN']);
		$this->assertNotNull($item['ID']);
		
		$refetchedItem = EarthIT_CMIPREST_Util::getItemById($this->storage, $resourceRc, $item['ID']);
		$this->assertEquals( $item, $refetchedItem );
	}
	
	public function testPatch() {
		$resourceRc = $this->schema->getResourceClass('resource');
		$urn0 = 'data:text/plain,'.rand(1000000,9999999);
		$item0 = $this->storage->postItem( $resourceRc, array('URN' => $urn0) );
		$urn1 = 'data:text/plain,'.rand(1000000,9999999);
		$item1 = $this->storage->putItem( $resourceRc, $item0['ID'], array('URN' => $urn1) );
		
		$this->assertEquals( $urn1, $item1['URN'] );
		
		$refetchedItem = EarthIT_CMIPREST_Util::getItemById($this->storage, $resourceRc, $item1['ID']);
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
		
		$refetchedItem = EarthIT_CMIPREST_Util::getItemById($this->storage, $resourceRc, $item1['ID']);
		$this->assertEquals( $item1, $refetchedItem );
	}
	
	public function testDelete() {
		$resourceRc = $this->schema->getResourceClass('resource');
		$urn = 'data:text/plain,'.rand(1000000,9999999);
		$item = $originalItem = $this->storage->postItem( $resourceRc, array('URN' => $urn) );
		$id = $item['ID'];
		
		// Should still be there for now
		$item = EarthIT_CMIPREST_UTil::getItemById($this->storage, $resourceRc, $id);
		$this->assertNotNull( $item );
		$this->assertEquals( $urn, $item['URN'] );
		
		// Until we delete it
		$this->storage->deleteItem( $resourceRc, $id );
		
		// Then it should no longer exist
		$item = EarthIT_CMIPREST_UTil::getItemById($this->storage, $resourceRc, $id);
		$this->assertNull( $item );
		
		// Deleting it again should not be a problem
		$this->storage->deleteItem( $resourceRc, $id );
		
		// It should still no longer exist
		$item = EarthIT_CMIPREST_UTil::getItemById($this->storage, $resourceRc, $id);
		$this->assertNull( $item );
		
		// If we put it back...
		$this->storage->putItem( $resourceRc, $id, $originalItem ); 
		
		// It should exist again
		$item = EarthIT_CMIPREST_UTil::getItemById($this->storage, $resourceRc, $id);
		$this->assertNotNull( $item );
		$this->assertEquals( $urn, $item['URN'] );
		
		// Deleting it yet again should, again, not be a problem
		$this->storage->deleteItem( $resourceRc, $id );
		
		// It should be deleted, again
		$item = EarthIT_CMIPREST_UTil::getItemById($this->storage, $resourceRc, $id);
		$this->assertNull( $item );
	}
}
