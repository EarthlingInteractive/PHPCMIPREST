<?php

class EarthIT_CMIPREST_RESTerTest extends PHPUnit_Framework_TestCase
{
	protected $dbAdapter;
	protected $storage;
	protected $rester;
	
	public function setUp() {
		// Relative to the pwd, yes.
		$dbConfigFile = 'config/test-dbc.json';
		$dbConfigJson = file_get_contents($dbConfigFile);
		if( $dbConfigJson === null ) {
			throw new Exception("Failed to load database config from $dbConfigFile");
		}
		$dbConfig = EarthIT_JSON::decode($dbConfigJson);
		$this->dbAdapter = Doctrine\DBAL\DriverManager::getConnection($dbConfig);
		$this->storage = new EarthIT_CMIPREST_PostgresStorage($this->dbAdapter, new EarthIT_DBC_PostgresNamer());
		$this->rester = new EarthIT_CMIPREST_RESTer(array(
			'storage' => $this->storage,
			'schema' => (require 'test-schema.php')
		));
	}
	
	public function testAbc() {
		print_r($this->dbAdapter->fetchAll('SELECT * FROM rating'));
		$this->fail("Nothing workx");
	}
}
