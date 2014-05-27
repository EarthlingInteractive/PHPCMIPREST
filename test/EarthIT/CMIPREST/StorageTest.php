<?php

abstract class EarthIT_CMIPREST_StorageTest extends PHPUnit_Framework_TestCase
{
	protected $storage;
	protected $schema;
	
	protected abstract function createStorage();
	
	public function setUp() {
		$this->storage = $this->createStorage();
		$this->schema = require 'test-schema.php';
	}
	
	public function testStoragePost() {
		$resourceRc = $this->schema->getResourceClass('resource');
		$itemId = $this->storage->postItem( $resourceRc, array('URN' => 'data:text/plain,'.rand(1000000,9999999)) );
		$this->assertNotNull($itemId);
		// TODO: Get it back and stuff
	}
}
