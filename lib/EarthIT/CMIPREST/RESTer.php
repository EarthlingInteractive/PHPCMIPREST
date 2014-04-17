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

	//// Class location
	
	protected $dbNamespacePath = array();
	public function setDbNamespacePath( $path ) {
		if( is_scalar($path) ) $path = explode('.', $path);
		if( !is_array($path) ) {
			throw new Exception("DB namespace path must be an array of strings or a single period.delimited.string.");
		}
		$this->dbNamespacePath = $path;
	}
	
	/**
	 * Return an EarthIT_DBC_SQLExpression that identifies the table.
	 * If a dbNamespacePath has been configured, it will be part of the expression.
	 */
	protected function rcTableExpression( EarthIT_Schema_ResourceClass $rc ) {
		$components = array();
		foreach( $this->dbNamespacePath as $ns ) {
			$components[] = new EarthIT_DBC_SQLIdentifier($ns);
		}
		foreach( $rc->getDbNamespacePath() as $ns ) {
			$components[] = new EarthIT_DBC_SQLIdentifier($ns);
		}
		$components[] = new EarthIT_DBC_SQLIdentifier($rc->getTableNameOverride() ?: $this->registry->getDbNamer()->getTableName($rc));
		return new EarthIT_DBC_SQLNamespacePath($components);
	}
		
	//// Field conversion
	
	protected function fieldRestName( EarthIT_Schema_ResourceClass $rc, EarthIT_Schema_Field $f ) {
		// When emitting JSON, format names as the JS does
		return EarthIT_Schema_WordUtil::toCamelCase($f->getName());
	}
	
	protected function fieldDbName( EarthIT_Schema_ResourceClass $rc, EarthIT_Schema_Field $f ) {
		return $f->getColumnNameOverride() ?: $this->registry->getDbNamer()->getColumnName( $rc, $f );
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
			$columnName = $this->fieldDbName($rc, $f);
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
		$userId = $crr->getUserId();
		$resourceClass = $this->registry->getSchema()->getResourceClass( EarthIT_Schema_WordUtil::depluralize($crr->getResourceCollectionName()) );
		
		if( !$resourceClass->hasRestService() ) {
			throw new EarthIT_CMIPREST_ResourceNotExposedViaService("'".$resourceClass->getName()."' records are not exposed via services");
		}
		
		switch( $crr->getMethod() ) {
		case 'GET': case 'HEAD':
			if( $itemId = $crr->getResourceInstanceId() ) {
				return new EarthIT_CMIPREST_UserAction_GetItemAction( $userId, $resourceClass, $itemId ); 
			} else {
				// TODO: Parse search parameters
				$sp = new EarthIT_CMIPREST_SearchParameters( array(), array(), 0, null );
				return new EarthIT_CMIPREST_UserAction_SearchAction( $userId, $resourceClass, $sp );
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
	 * Determine if an action is allowed before actually doing it.
	 * For search actions, this may return null to indicate that
	 * authorization requires the actual search results, which will be passed
	 * to postAuthorizeSearchResult to determine is they are allowed.
	 */
	protected function preAuthorizeAction( EarthIT_CMIPREST_UserAction $act, array &$explanation ) {
		// TODO: Move implementation to a separate permission checker class
		$rc = $act->getResourceClass();
		$rcName = $rc->getName();
		if( $rc->membersArePublic() ) {
			// TODO: this only means visible.
			// It shouldn't allow them to do anything besides searching and getting
			$explanation[] = "{$rcName} records are public";
			return true;
		} else {
			$explanation[] = "{$rcName} records are NOT public";
			return false;
		}
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
			$tableExpression = $this->rcTableExpression( $resourceClass );
			$builder = new EarthIT_DBC_DoctrineStatementBuilder($this->registry->getDbAdapter());
			$stmt = $builder->makeStatement("SELECT * FROM {table}", array('table'=>$tableExpression));
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
			
			// TODO: Remove this awful hack that I put in for the presentation
			if( preg_match('/with=(.*)/',$_SERVER['REQUEST_URI'],$bif) ) {
				$withStuff = explode(',', $bif[1]);
			} else $withStuff = array();
			
			if( in_array('user',$withStuff) ) {
				$userRows = $this->registry->getDbAdapter()->query('SELECT * FROM "user"')->fetchAll();
				$users = array();
				foreach( $userRows as $ur ) {
					$users[$ur['id']] = $ur;
				}
				foreach( $results as $k=>$res ) {
					$results[$k]['user'] = $users[$res['userid']];
				}
			}
			if( in_array('organization',$withStuff) ) {
				$organizationRows = $this->registry->getDbAdapter()->query('SELECT * from "organization"')->fetchAll();
				$organizations = array();
				foreach( $organizationRows as $ur ) {
					$organizations[$ur['id']] = $ur;
				}
				foreach( $results as $k=>$res ) {
					$results[$k]['organization'] = $organizations[$res['organizationid']];
				}
			}
			
			
			return $results;
		}
		
		if( $preAuth !== true ) {
			throw new Exception("preAuthorizeAction should only return true or false for non-search actions, but it returned ".var_export($auth,true));
		}
		
		// Otherwise it's A-Okay!
		
		if( $act instanceof EarthIT_CMIPREST_UserAction_GetItemAction ) {
			$resourceClass = $act->getResourceClass();
			$tableExpression = $this->rcTableExpression( $resourceClass );
			$builder = new EarthIT_DBC_DoctrineStatementBuilder($this->registry->getDbAdapter());
			$stmt = $builder->makeStatement("SELECT * FROM {table} WHERE {pkCondition}", array(
				'table'=>$tableExpression,
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
			$tableExpression = $this->rcTableExpression( $resourceClass );
			
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
				'table' => $tableExpression,
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
	
	public static function errorStructure( $message, array $notes=array() ) {
		return array(
			'errors' => array(
				array(
					'message' => $message,
					'notes' => $notes
				)
			)
		);
	}
	
	public static function errorResponse( $status, $message, array $notes=array() ) {
		$result = self::errorStructure( $message, $notes );
		return Nife_Util::httpResponse( $status, new EarthIT_JSON_PrettyPrintedJSONBlob($result), 'application/json' );
	}
	
	public function handle( EarthIT_CMIPREST_CMIPRESTRequest $crr ) {
		// TODO: Put exception -> response mapping in its own function
		
		try {
			$act = $this->cmipRequestToUserAction($crr);
			$result = $this->doAction($act);
			return Nife_Util::httpResponse( 200, new EarthIT_JSON_PrettyPrintedJSONBlob($result), 'application/json' );
		} catch( EarthIT_CMIPREST_ActionUnauthorized $un ) {
			$status = $act->getUserId() === null ? 401 : 403;
			return self::errorResponse( $status, $un->getAction()->getActionDescription(), $un->getNotes() );
		} catch( EarthIT_Schema_NoSuchResourceClass $un ) {
			return self::errorResponse( 404, $un->getMessage() );
		} catch( EarthIT_CMIPREST_ResourceNotExposedViaService $un ) {
			return self::errorResponse( 404, $un->getMessage() );
		}
	}
}
