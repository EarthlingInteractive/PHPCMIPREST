<?php

class EarthIT_CMIPREST_RESTer extends EarthIT_Component
{
	protected function restName( EarthIT_Schema_ResourceClass $rc, EarthIT_Schema_Field $f ) {
		return $f->getName();
		// But maybe Josh will prefer something else!
	}
	
	protected function translateDbFieldsToRest( EarthIT_Schema_ResourceClass $rc, array $dbRecord ) {
		$columnNamer = $this->registry->getDbNamer();
		$result = array();
		foreach( $rc->getFields() as $f ) {
			$columnName = $columnNamer->getColumnName( $rc, $f );
			if( isset($dbRecord[$columnName]) ) {
				$result[$f->getName()] = $dbRecord[$columnName];
			}
		}
		// TODO: Need to add 'ID' column
		return $result;
	}
	
	public function handle( EarthIT_CMIPREST_CMIPRESTRequest $crr ) {
		// TODO: support searches, getting a single item by ID, putting, posting, and deleting.
		// TODO: include some hook for permission checking
		$resourceClass = $this->registry->getSchema()->getResourceClass( EarthIT_Schema_WordUtil::depluralize($crr->getResourceCollectionName()) );
		$tableName = $this->registry->getDbNamer()->getTableName( $resourceClass );
		$builder = new EarthIT_DBC_DoctrineStatementBuilder($this->registry->getDbAdapter());
		$stmt = $builder->makeStatement("SELECT * FROM {table}", array('table'=>new EarthIT_DBC_SQLIdentifier($tableName)));
		$stmt->execute();
		$results = array();
		foreach( $stmt->fetchAll() as $row ) {
			$results[] = $this->translateDbFieldsToRest($resourceClass, $row);
		}
		return Nife_Util::httpResponse( 200, new EarthIT_JSON_PrettyPrintedJSONBlob($results) );
	}
}
