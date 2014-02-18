<?php

class EarthIT_CMIPREST_RESTer extends EarthIT_Component
{
	protected static function dbValueToPhpValue( $value, $php_type ) {
		switch( $php_type ) {
		case 'string': return (string)$value;
		case 'int': return (int)$value;
		case 'bool': return (bool)$value;
		default:
			throw new Exception("Don't know how to convert '$value' to PHP type '$php_type'.");
		}
	}

	protected static function getIdRegex( EarthIT_Schema_ResourceClass $rc ) {
		$pk = $rc->getPrimaryKey();
		if( $pk === null ) throw new Exception("No ID regex because no primary key for ".$rc->getName().".");
		
		$fields = $rc->getFields();
		$parts = array();
		foreach( $pk->getFieldNames() as $fn ) {
			$field = $fields[$fn];
			$datatype = $field->getType();
			$fRegex = $datatype->getRegex();
			if( $fRegex === null ) {
				throw new Exception("Can't build ID regex because ID component field '$fn' is of type '".$datatype->getName()."', which doesn't have a regex.");
			}
			$parts[] = "($fRegex)";
		}
		return implode("-", $parts);
	}

	/**
	 * return array of field name => field value for the primary key fields encoded in $id
	 */
	protected static function idToFieldValues( EarthIT_Schema_ResourceClass $rc, $id) {
		$idRegex = self::getIdRegex( $rc );
		if( !preg_match('/^'.$idRegex.'$/', $id, $bif) ) {
			throw new Exception("ID did not match regex /^$idRegex\$/: $id");
		}
		
		$idFieldValues = array();
		$pk = $rc->getPrimaryKey();
		$fields = $rc->getFields();
		$i = 1;
		foreach( $pk->getFieldNames() as $fn ) {
			$field = $fields[$fn];
			$idFieldValues[$fn] = self::dbValueToPhpValue($bif[$i], $field->getType()->getPhpTypeName());
			++$i;
		}
		
		return $idFieldValues;
	}

	protected function restName( EarthIT_Schema_ResourceClass $rc, EarthIT_Schema_Field $f ) {
		// Josh's priorities with respect to naming:
		// 1) field names in JSON responses and query parameters should be identical
		// 2) field names in JSON/query parameters should match those in the database
		// 
		// Ideally JSON fields would be camelCase to match JS objects.
		// But I want to avoid camelCase in URLs.
		// If we use Postgres, all identifiers will be sqishedtogetherlowercase
		// Therefore, use squishedtogetherlowercase.
		return EarthIT_Schema_WordUtil::minimize($f->getName());
	}
	
	protected function dbRecordToRestObject( EarthIT_Schema_ResourceClass $rc, array $dbRecord ) {
		$columnNamer = $this->registry->getDbNamer();
		$result = array();
		foreach( $rc->getFields() as $f ) {
			$columnName = $columnNamer->getColumnName( $rc, $f );
			if( isset($dbRecord[$columnName]) ) {
				$result[$this->restName($rc, $f)] = $dbRecord[$columnName];
			}
		}
		// TODO: Need to add 'id' column in cases where the primary key is different
		return $result;
	}
	
	protected function cmipRequestToUserAction( EarthIT_CMIPREST_CMIPRESTRequest $crr ) {
		$userId = null; // Where to get it???
		$resourceClass = $this->registry->getSchema()->getResourceClass( EarthIT_Schema_WordUtil::depluralize($crr->getResourceCollectionName()) );
		switch( $crr->getMethod() ) {
		case 'GET': case 'HEAD':
			if( $itemId = $crr->getResourceInstanceId() ) {
				return new EarthIT_CMIPREST_UserAction_GetItemAction( $userId, $resourceClass, $itemId ); 
			} else {
				// TODO: Parse search parameters
				return new EarthIT_CMIPREST_UserAction_SearchAction( $userId, $resourceClass, new EarthIT_CMIPREST_SearchParameters() ); 
			}
		case 'PUT': case 'POST': case 'DELETE':
			// TODO
			throw new Exception("PUT/POST/DELETE requests not yet supported");
		}
	}
	
	protected function makeIdMatchingExpression( EarthIT_Schema_ResourceClass $rc, $id ) {
		$fields = $rc->getFields();
		$fieldValues = self::idToFieldValues( $rc, $id );
		$columnNamer = $this->registry->getDbNamer();
		
		$conditionTemplates = array();
		$conditionParameters = array();
		foreach( $fieldValues as $fieldName => $value ) {
			$field = $fields[$fieldName];
			$conditionTemplates[] = $columnNamer->getColumnName($rc,$field). ' = {'.$fieldName.'}';
			$conditionParameters[$fieldName] = $value;
		}
		return new EarthIT_DBC_BaseSQLExpression( implode(" AND ", $conditionTemplates), $conditionParameters );
	}
	
	/**
	 * Result will be a JSON array in REST form
	 */
	protected function doAction( EarthIT_CMIPREST_UserAction $act ) {
		// TODO: include some hook for permission checking
		
		if( $act instanceof EarthIT_CMIPREST_UserAction_SearchAction ) {
			$resourceClass = $act->getResourceClass();
			$tableName = $this->registry->getDbNamer()->getTableName( $resourceClass );
			$builder = new EarthIT_DBC_DoctrineStatementBuilder($this->registry->getDbAdapter());
			$stmt = $builder->makeStatement("SELECT * FROM {table}", array('table'=>new EarthIT_DBC_SQLIdentifier($tableName)));
			$stmt->execute();
			$results = array();
			foreach( $stmt->fetchAll() as $row ) {
				$results[] = $this->dbRecordToRestObject($resourceClass, $row);
			}
			return $results;
		} else if( $act instanceof EarthIT_CMIPREST_UserAction_GetItemAction ) {
			$resourceClass = $act->getResourceClass();
			$tableName = $this->registry->getDbNamer()->getTableName( $resourceClass );
			$builder = new EarthIT_DBC_DoctrineStatementBuilder($this->registry->getDbAdapter());
			$stmt = $builder->makeStatement("SELECT * FROM {table} WHERE {pkCondition}", array(
				'table'=>new EarthIT_DBC_SQLIdentifier($tableName),
				'pkCondition'=>$this->makeIdMatchingExpression($resourceClass, $act->getItemId())
			));
			$stmt->execute();
			$result = null;
			foreach( $stmt->fetchAll() as $row ) {
				// Expecting only one!  Or zero.
				$result = $this->dbRecordToRestObject($resourceClass, $row);
			}
			return $result;
		} else {
			// TODO
			throw new Exception(get_class($act)." not supported");
		}
	}
	
	public function handle( EarthIT_CMIPREST_CMIPRESTRequest $crr ) {
		$act = $this->cmipRequestToUserAction($crr);
		$result = $this->doAction($act);
		return Nife_Util::httpResponse( 200, new EarthIT_JSON_PrettyPrintedJSONBlob($result), 'application/json' );
	}
}
