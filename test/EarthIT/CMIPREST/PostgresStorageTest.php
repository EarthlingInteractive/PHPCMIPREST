<?php

class EarthIT_CMIPREST_PostgresStorageTest extends EarthIT_CMIPREST_StorageTest
{
	protected function createStorage() {
		// Relative to the pwd, yes.
		$dbConfigFile = 'config/test-dbc.json';
		$dbConfigJson = file_get_contents($dbConfigFile);
		if( $dbConfigJson === null ) {
			throw new Exception("Failed to load database config from $dbConfigFile");
		}
		$dbConfig = EarthIT_JSON::decode($dbConfigJson);
		$this->dbAdapter = Doctrine\DBAL\DriverManager::getConnection($dbConfig);
		return new EarthIT_CMIPREST_PostgresStorage($this->dbAdapter, $this->schema);
	}
}
