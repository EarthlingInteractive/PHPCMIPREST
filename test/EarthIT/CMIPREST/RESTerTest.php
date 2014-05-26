<?php

class EarthIT_CMIPREST_RESTerTest extends PHPUnit_Framework_TestCase
{
	public function setUp() {
		// Relative to the pwd, yes.
		$dbConfigFile = 'config/test-dbc.json';
		$dbConfigJson = file_get_contents($dbConfigFile);
		if( $dbConfigJson === null ) {
			throw new Exception("Failed to load database config from $dbConfigFile");
		}
		$dbConfig = EarthIT_JSON::decode($dbConfigJson);
		$this->rester = new EarthIT_CMIPREST_RESTer(array(
			'dbAdapter' => Doctrine\DBAL\DriverManager::getConnection( $dbConfig ),
			'dbNamer' => new EarthIT_DBC_PostgresNamer(),
			'schema' => (require 'test-schema.php')
		));
	}
	
	public function testAbc() {
		$this->fail("Nothing workx");
	}
}
