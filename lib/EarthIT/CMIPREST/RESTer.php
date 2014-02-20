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
		if( $pk === null or count($pk->getFieldNames()) == 0 ) {
			throw new Exception("No ID regex because no primary key for ".$rc->getName().".");
		}
		
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

	//// Field conversion
	
	protected function fieldRestName( EarthIT_Schema_ResourceClass $rc, EarthIT_Schema_Field $f ) {
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
	
	protected function fieldDbName( EarthIT_Schema_ResourceClass $rc, EarthIT_Schema_Field $f ) {
		return $this->registry->getDbNamer()->getColumnName( $rc, $f );
	}
	
	//// Value conversion
	
	protected function restValueToInternal( EarthIT_Schema_DataType $dt, $v ) {
		return $v;
	}
	
	protected function restFieldsToInternal( EarthIT_Schema_ResourceClass $rc, array $restObj ) {
		$internal = array();
		foreach( $rc->getFields() as $field ) {
			$frn = $this->fieldRestName( $rc, $field );
			if( isset($restObj[$frn]) ) {
				$internal[$field->getName()] = $this->restValueToInternal( $field->getType(), $restObj[$frn] );
			}
		}
		return $internal;
	}
	
	//// Object conversion
	
	protected function dbObjectToRest( EarthIT_Schema_ResourceClass $rc, array $columnValues ) {
		$columnNamer = $this->registry->getDbNamer();
		$result = array();
		foreach( $rc->getFields() as $f ) {
			$columnName = $columnNamer->getColumnName( $rc, $f );
			if( isset($columnValues[$columnName]) ) {
				$result[$this->fieldRestName($rc, $f)] = $columnValues[$columnName];
			}
		}
		// TODO: Need to add 'id' column in cases where the primary key is different
		return $result;
	}
	
	protected function internalObjectToDb( EarthIT_Schema_ResourceClass $rc, array $obj ) {
		$columnNamer = $this->registry->getDbNamer();
		$columnValues = array();
		foreach( $rc->getFields() as $f ) {
			$fn = $f->getName();
			if( isset($obj[$fn]) ) {
				$cn = $columnNamer->getColumnName($rc, $f);
				$columnValues[$cn] = $obj[$fn];
			}
		}
		return $columnValues;
	}
	
	//// Action conversion
	
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
		case 'POST':
			if( $crr->getResourceInstanceId() !== null ) {
				throw new Exception("You may not include item ID when POSTing");
			}
			return new EarthIT_CMIPREST_UserAction_PostItemAction(
				$userId, $resourceClass,
				$this->restFieldsToInternal($resourceClass, $crr->getContent())
			);
		case 'PUT':
			if( $crr->getResourceInstanceId() === null ) {
				throw new Exception("You ust include item ID when PUTing");
			}
			return new EarthIT_CMIPREST_UserAction_PutItemAction(
				$userId, $resourceClass, $crr->getResourceInstanceId(),
				$this->restFieldsToInternal($resourceClass, $crr->getContent())
			);
		case 'PATCH':
			if( $crr->getResourceInstanceId() === null ) {
				throw new Exception("You ust include item ID when PATCHing");
			}
			return new EarthIT_CMIPREST_UserAction_PatchItemAction(
				$userId, $resourceClass, $crr->getResourceInstanceId(),
				$this->restFieldsToInternal($resourceClass, $crr->getContent())
			);
		case 'DELETE':
			if( $crr->getResourceInstanceId() === null ) {
				throw new Exception("You ust include item ID when PATCHing");
			}
			return new EarthIT_CMIPREST_UserAction_DeleteItemAction( $userId, $resourceClass, $crr->getResourceInstanceId() );
		default:
			throw new Exception("Unrecognized method, '".$crr->getMethod()."'");
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
	 * Determine if an action is allowed without actually doing it.
	 * For search actions, this may return null to indicate that
	 * authorization requires the actual search results, which will be passed
	 * to postAuthorizeSearchResult to determine is they are allowed.
	 */
	protected function preAuthorizeAction( EarthIT_CMIPREST_UserAction $act, array &$explanation ) {
		return true;
	}
	
	protected function postAuthorizeSearchResult( $userId, EarthIT_Schema_ResourceClass $rc, array $itemData, array &$explanation ) {
		return true;
	}
	
	/**
	 * Result will be a JSON array in REST form
	 */
	protected function doAction( EarthIT_CMIPREST_UserAction $act ) {
		$authorizationExplanation = array();
		$preAuth = $this->preAuthorizeAction($act, $authorizationExplanation);
		
		if( $preAuth === false ) {
			throw new EarthIT_CMIPREST_ActionUnauthorized($act, $authorizationExplanation);
		}
		
		if( $act instanceof EarthIT_CMIPREST_UserAction_SearchAction ) {
			$resourceClass = $act->getResourceClass();
			$tableName = $this->registry->getDbNamer()->getTableName( $resourceClass );
			$builder = new EarthIT_DBC_DoctrineStatementBuilder($this->registry->getDbAdapter());
			$stmt = $builder->makeStatement("SELECT * FROM {table}", array('table'=>new EarthIT_DBC_SQLIdentifier($tableName)));
			$stmt->execute();
			$results = array();
			$rows = $stmt->fetchAll();

			foreach( $rows as $row ) {
				if( !$preAuth ) {
					$obj = $this->dbRecordToInternal($resourceClass, $row);
					if( !$this->postAuthorizeSearchResult($act->getUserId(), $resourceClass, $item, $authorizationExplanation) ) {
						throw new EarthIT_CMIPREST_ActionUnauthorized($act, $authorizationExplanation);
					}
				}
				$results[] = $this->dbObjectToRest($resourceClass, $row);
			}
			
			return $results;
		}
		
		if( $preAuth !== true ) {
			throw new Exception("preAuthorizeAction should only return true or false for non-search actions, but it returned ".var_export($auth,true));
		}
		
		// Otherwise it's A-Okay!
		
		if( $act instanceof EarthIT_CMIPREST_UserAction_GetItemAction ) {
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
				$result = $this->dbObjectToRest($resourceClass, $row);
			}
			return $result;
		} else if( $act instanceof EarthIT_CMIPREST_UserAction_PostItemAction ) {
			$resourceClass = $act->getResourceClass();
			$tableName = $this->registry->getDbNamer()->getTableName( $resourceClass );
			
			$columnValues = $this->internalObjectToDb($resourceClass, $act->getItemData());
			$columnExpressionList = array();
			$columnValueList = array();
			foreach( $columnValues as $columnName => $value ) {
				$columnExpressionList[] = new EarthIT_DBC_SQLIdentifier($columnName);
				$columnValueList[] = $value;
			}
			
			// TODO: actually determine ID columns
			
			$builder = new EarthIT_DBC_DoctrineStatementBuilder($this->registry->getDbAdapter());
			$stmt = $builder->makeStatement("INSERT INTO {table} {columns} VALUES {values} RETURNING id", array(
				'table' => new EarthIT_DBC_SQLIdentifier($tableName),
				'columns' => $columnExpressionList,
				'values' => $columnValueList
			));
			$stmt->execute();
			$result = null;
			foreach( $stmt->fetchAll() as $row ) {
				// Expecting only one!  Or zero.
				return $row['id'];
			}
			throw new Exception("INSERT...RETURNING didn't do what we expected");
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
