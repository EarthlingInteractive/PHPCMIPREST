<?php

class EarthIT_CMIPREST_RESTerTest extends PHPUnit_Framework_TestCase
{
	protected $dbAdapter;
	protected $storage;
	protected $rester;
	protected $schema;
	
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
	}
	
	public function testAbc() {
		$this->fail("Nothing workx");
	}
}
