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
		$item = EarthIT_CMIPREST_Util::getItemById($this->storage, $resourceRc, $id);
		$this->assertNotNull( $item );
		$this->assertEquals( $urn, $item['URN'] );
		
		// Until we delete it
		$this->storage->deleteItem( $resourceRc, $id );
		
		// Then it should no longer exist
		$item = EarthIT_CMIPREST_Util::getItemById($this->storage, $resourceRc, $id);
		$this->assertNull( $item );
		
		// Deleting it again should not be a problem
		$this->storage->deleteItem( $resourceRc, $id );
		
		// It should still no longer exist
		$item = EarthIT_CMIPREST_Util::getItemById($this->storage, $resourceRc, $id);
		$this->assertNull( $item );
		
		// If we put it back...
		$this->storage->putItem( $resourceRc, $id, $originalItem ); 
		
		// It should exist again
		$item = EarthIT_CMIPREST_Util::getItemById($this->storage, $resourceRc, $id);
		$this->assertNotNull( $item );
		$this->assertEquals( $urn, $item['URN'] );
		
		// Deleting it yet again should, again, not be a problem
		$this->storage->deleteItem( $resourceRc, $id );
		
		// It should be deleted, again
		$item = EarthIT_CMIPREST_Util::getItemById($this->storage, $resourceRc, $id);
		$this->assertNull( $item );
	}
	
	public function testJohnlySearch() {
		$resourceRc = $this->schema->getResourceClass('resource');
		$personRc   = $this->schema->getResourceClass('person');
		$ratingRc   = $this->schema->getResourceClass('rating');
		
		$resource = $this->storage->postItem($resourceRc, array('URN'=>'data:text/plain,'.rand(1000000,9999999)));
		$person   = $this->storage->postItem($personRc,   array('first name' => 'John', 'last name' => 'Haas'));
		$rating   = $this->storage->postItem($ratingRc,   array(
			'author ID'  => $person['ID'],
			'subject ID' => $resource['ID'],
			'comment'    => 'My favorite number!  Very Radiohead',
			'quality rating' => 85,
			'resource is fake' => false,
			'mood' => null
		));
		
		// Yay it's stored.  Let's try to load it.
		$search = new EarthIT_Storage_Search(
			$ratingRc,
			EarthIT_Storage_ItemFilters::byId($person['ID'].'-'.$resource['ID'], $ratingRc) );
		
		$objectNamer = function($sobj) { return $sobj->getName(); };
		
		$johnBranches = EarthIT_CMIPREST_RequestParser_Util::withsToJohnBranches(
			$this->schema, $ratingRc,
			'author,subject', $objectNamer, '.');
		
		$rez = $this->storage->johnlySearchItems( $search, $johnBranches, array() );
		$this->assertEquals( array(
			'root' => array( $rating ),
			'root.author' => array( $person ),
			'root.subject' => array( $resource )
		), $rez);
		
		// This time with an order-by
		$search = new EarthIT_Storage_Search(
			$ratingRc,
			EarthIT_Storage_ItemFilters::byId($person['ID'].'-'.$resource['ID'], $ratingRc),
			EarthIT_Storage_FieldwiseComparator::parse('author ID') );
		
		$rez = $this->storage->johnlySearchItems( $search, $johnBranches, array() );
		$this->assertEquals( array(
			'root' => array( $rating ),
			'root.author' => array( $person ),
			'root.subject' => array( $resource )
		), $rez);
	}
}
