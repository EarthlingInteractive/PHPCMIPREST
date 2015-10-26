<?php

class EarthIT_CMIPREST_PostgresStorageTest extends EarthIT_CMIPREST_StorageTest
{
	protected function createStorage() {
		// Relative to the pwd, yes.
		$dbConfigFile = $this->configDir.'/dbc.json';
		$dbConfigJson = file_get_contents($dbConfigFile);
		if( $dbConfigJson === null ) {
			throw new Exception("Failed to load database config from $dbConfigFile");
		}
		$dbConfig = EarthIT_JSON::decode($dbConfigJson);
		$this->schema = $this->loadTestSchema();
		$this->sqlRunner = new EarthIT_DBC_DoctrineSQLRunner(Doctrine\DBAL\DriverManager::getConnection($dbConfig));
		$this->dbObjectNamer = new EarthIT_DBC_PostgresNamer();
		return new EarthIT_CMIPREST_SQLStorage(
			$this->schema,
			$this->sqlRunner,
			$this->dbObjectNamer,
			new EarthIT_Storage_PostgresSQLGenerator($this->dbObjectNamer));
	}
}
