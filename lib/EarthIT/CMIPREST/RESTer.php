<?php

class EarthIT_CMIPREST_John {
	public $originResourceClass;
	public $originLinkFields;
	public $targetResourceClass;
	public $targetLinkFields;
	public $targetIsPlural;
	
	public function __construct(
		EarthIT_Schema_ResourceClass $originRc, array $originFields,
		EarthIT_Schema_ResourceClass $targetRc, array $targetFields,
		$targetIsPlural
	) {
		$this->originResourceClass = $originRc; $this->originLinkFields = $originFields;
		$this->targetResourceClass = $targetRc; $this->targetLinkFields = $targetFields;
		$this->targetIsPlural = $targetIsPlural;
	}
	
	public function targetIsPlural() { return $this->targetIsPlural; }
}

class EarthIT_CMIPREST_JohnTreeNode
{
	public $john;
	/** array of key => JohnTreeNode */
	public $branches;
	
	public function __construct( EarthIT_CMIPREST_John $john, array $branches ) {
		$this->john = $john;
		$this->branches = $branches;
	}
	
	public function getJohn() { return $this->john; }
	public function getBranches() { return $this->branches; }
}

/*
 * TODO:
 * - Make REST field namer configurable
 * - Comment functions better
 * - Split into 2 or 3 independent objects
 *   CMIPRESTRequest -> UserAction translator
 *   UserAction -> I/O
 */

class EarthIT_CMIPREST_RESTer extends EarthIT_Component
{
	protected static function dbToPhpValue( $value, $phpType ) {
		// May want to do something different than just use cast
		// e.g. in case we want to interpret "010" as ten instead of eight.
		return EarthIT_CMIPREST_Util::cast( $value, $phpType );
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
			$idFieldValues[$fn] = self::dbToPhpValue($bif[$i], $field->getType()->getPhpTypeName());
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
				$dataType = $f->getType();
				$phpTypeName = $dataType === null ? null : $dataType->getPhpTypeName();
				$result[$this->fieldRestName($rc, $f)] = self::dbToPhpValue($columnValues[$columnName], $phpTypeName);
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
	
	protected function getFieldsByRestName( EarthIT_Schema_ResourceClass $rc ) {
		$fbrn = array();
		foreach( $rc->getFields() as $f ) {
			$fbrn[$this->fieldRestName($rc, $f)] = $f;
		}
		return $fbrn;
	}
	
	//// Action conversion
	
	protected function parseFieldMatcher( $v ) {
		$colonIdx = strpos($v, ':');
		if( $colonIdx === false ) {
			return (strpos($v, '*') === false) ?
				new EarthIT_CMIPREST_FieldMatcher_Equal($v) :
				new EarthIT_CMIPREST_FieldMatcher_Like($v);
		} else {
			$scheme = substr($v, 0, $colonIdx);
			$pattern = substr($v, $colonIdx+1) ?: ''; // Because substr('xyz',3) returns false. #phpwtf
			switch( $scheme ) {
			case 'eq': return new EarthIT_CMIPREST_FieldMatcher_Equal($pattern);
			case 'ne': return new EarthIT_CMIPREST_FieldMatcher_NotEqual($pattern);
			case 'gt': return new EarthIT_CMIPREST_FieldMatcher_Greater($pattern);
			case 'ge': return new EarthIT_CMIPREST_FieldMatcher_GreaterOrEqual($pattern);
			case 'lt': return new EarthIT_CMIPREST_FieldMatcher_Lesser($pattern);
			case 'le': return new EarthIT_CMIPREST_FieldMatcher_LesserOrEqual($pattern);
			case 'like': return new EarthIT_CMIPREST_FieldMatcher_Like($pattern);
			case 'in': return new EarthIT_CMIPREST_FieldMatcher_In($pattern === '' ? array() : explode(',',$pattern));
			default:
				throw new Exception("Unrecognized field match scheme: '$scheme'");
			}
		}
	}
	
	protected function parseOrderByComponents( EarthIT_Schema_ResourceClass $rc, $v ) {
		$fieldsByRestName = $this->getFieldsByRestName($rc);
		$oorderByComponents = array();
		foreach( explode(',',$v) as $cv ) {
			if( $cv[0] == '+' ) {
				$ascending = true;
				$cv = substr($cv,1);
			} else if( $cv[0] == '-' ) {
				$ascending = false;
				$cv = substr($cv,1);
			} else $ascending = true;
			
			// May eventually need to take fake fields into account, here
			if( !isset($fieldsByRestName[$cv]) ) {
				throw new Exception("Unknown field in orderBy: '$cv'");
			}
			$orderByComponents[] = new EarthIT_CMIPREST_OrderByComponent($fieldsByRestName[$cv], $ascending);
		}
		return $orderByComponents;
	}
	
	/**
	 * a.b.c.d -> { a: { b: { c: { d: {} } } } }
	 */
	protected static function parsePath( $path, array &$into ) {
		if( $path === '' ) return;
		if( is_string($path) ) $path = explode('.', $path);
		if( count($path) == 0 ) return;
		
		if( !isset($into[$path[0]]) ) {
			$into[$path[0]] = array();
		}
		
		self::parsePath( array_slice($path, 1), $into[$path[0]] );
	}
	
	protected static function getFields( EarthIT_Schema_ResourceClass $rc, array $fieldNames ) {
		$f = $rc->getFields();
		$fields = array();
		foreach( $fieldNames as $fn ) $fields[] = $f[$fn];
		return $fields;
	}
	
	protected function findJohnByRestName( EarthIT_Schema_ResourceClass $originRc, $linkRestName ) {
		foreach( $originRc->getReferences() as $refName=>$ref ) {
			$restName = EarthIT_Schema_WordUtil::toCamelCase($refName);
			if( $linkRestName == $restName ) {
				$targetClass = $this->registry->getSchema()->getResourceClass($ref->getTargetClassName());
				return new EarthIT_CMIPREST_John(
					$originRc, self::getFields($originRc, $ref->getOriginFieldNames()),
					$targetClass, self::getFields($targetClass, $ref->getTargetFieldNames()),
					false
				);
			}
		}
		throw new Exception("Can't find '$linkRestName' link from ".$originRc->getName());
	}
	
	protected function withsToJohnBranches( array $withs, EarthIT_Schema_ResourceClass $originRc ) {
		$branches = array();
		foreach( $withs as $k=>$subWiths ) {
			$john = $this->findJohnByRestName( $originRc, $k );
			$branches[$k] = new EarthIT_CMIPREST_JohnTreeNode(
				$john,
				$this->withsToJohnBranches( $subWiths, $john->targetResourceClass )
			);
		}
		return $branches;
	}
	
	protected function cmipRequestToUserAction( EarthIT_CMIPREST_CMIPRESTRequest $crr ) {
		$userId = $crr->getUserId();
		$resourceClass = $this->registry->getSchema()->getResourceClass( EarthIT_Schema_WordUtil::depluralize($crr->getResourceCollectionName()) );
		
		if( !$resourceClass->hasRestService() ) {
			throw new EarthIT_CMIPREST_ResourceNotExposedViaService("'".$resourceClass->getName()."' records are not exposed via services");
		}
		
		switch( $crr->getMethod() ) {
		case 'GET': case 'HEAD':
			$withs = array();
			foreach( $crr->getResultModifiers() as $k=>$v ) {
				if( $k === 'with' ) {
					$things = explode(',', $v);
					foreach( $things as $thing ) {
						self::parsePath(explode('.',$thing), $withs);
					}
				} else {
					throw new Exception("Unrecognized result modifier: '$k'");
				}
			}
			$johnBranches = $this->withsToJohnBranches( $withs, $resourceClass );
			
			if( $itemId = $crr->getResourceInstanceId() ) {
				return new EarthIT_CMIPREST_UserAction_GetItemAction( $userId, $resourceClass, $itemId, $johnBranches ); 
			} else {
				$fields = $resourceClass->getFields();
				$fieldRestToInternalNames = array();
				foreach( $fields as $fn=>$field ) {
					$fieldRestToInternalNames[$this->fieldRestName($resourceClass, $field)] = $fn;
				}
				
				$fieldMatchers = array();
				$orderBy = array();
				$skip = 0;
				$limit = null;
				foreach( $crr->getParameters() as $k=>$v ) {
					if( $k == '_' ) {
						// Ignore!
					} else if( $k == 'orderBy' ) {
						$orderBy = $this->parseOrderByComponents($resourceClass, $v);
					} else if( $k == 'limit' ) {
						if( preg_match('/^(\d+),(\d+)$/', $v, $bif ) ) {
							$skip = $bif[1]; $limit = $bif[2];
						} else if( preg_match('/^(\d+)$/', $v, $bif ) ) {
							$limit = $bif[1];
						} else {
							throw new Exception("Malformed skip/limit parameter: '$v'");
						}
					} else {
						// TODO: 'id' may need to be remapped to multiple field matchers
						// Will probably want to allow for other fake, searchable fields, too
						if( !isset($fieldRestToInternalNames[$k]) ) {
							throw new Exception("No such field: '$k'");
						}
						$fieldMatchers[$fieldRestToInternalNames[$k]] = self::parseFieldMatcher($v);
					}
				}
				$sp = new EarthIT_CMIPREST_SearchParameters( $fieldMatchers, $orderBy, $skip, $limit );
				return new EarthIT_CMIPREST_UserAction_SearchAction( $userId, $resourceClass, $sp, $johnBranches );
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
	
	protected function itemIdToSearchParameters( EarthIT_Schema_ResourceClass $rc, $id ) {
		$fieldValues = self::idToFieldValues( $rc, $id );
		$fieldMatchers = array();
		foreach( $fieldValues as $fieldName => $value ) {
			$fieldMatchers[$fieldName] = new EarthIT_CMIPREST_FieldMatcher_Equal($value);
		}
		return new EarthIT_CMIPREST_SearchParameters($fieldMatchers, array(), 0, null);
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
		// TODO: Same as above
		return true;
	}
	
	//// Searchy with= support stuff
	
	protected function buildSearchSql(
		EarthIT_Schema_ResourceClass $rc,
		EarthIT_CMIPREST_SearchParameters $sp,
		array &$params
	) {
		$fields = $rc->getFields();
		$whereClauses = array();
		$tableAlias = 'tab';
		$tableExpression = $this->rcTableExpression( $rc );
		$params['table'] = $tableExpression;
		$sql = "SELECT * FROM {table} AS {$tableAlias}";
		foreach( $sp->getFieldMatchers() as $fieldName => $matcher ) {
			$field = $fields[$fieldName];
			$columnName = $this->fieldDbName($rc, $field);
			$columnParamName = EarthIT_DBC_ParameterUtil::newParamName('column');
			$params[$columnParamName] = new EarthIT_DBC_SQLIdentifier($columnName);
			$columnExpression = "{$tableAlias}.{{$columnParamName}}";
			$matcherSql = $matcher->toSql( $columnExpression, $fields[$fieldName]->getType()->getPhpTypeName(), $params );
			if( $matcherSql === 'TRUE' ) {
				continue;
			} else if( $matcherSql === 'FALSE' ) {
				// There will be no results!
				return 'SELECT NOTHING';
			}
			$whereClauses[] = $matcherSql;
		}
		if( $whereClauses ) $sql .= "\nWHERE ".implode("\n  AND ",$whereClauses);
		if( count($orderByComponents = $sp->getOrderByComponents()) > 0 ) {
			$orderBySqlComponents = array();
			foreach( $orderByComponents as $oc ) {
				$orderBySqlComponents[] = $this->fieldDbName($rc, $oc->getField()).($oc->isAscending() ? " ASC" : " DESC");
			}
			$sql .= "\nORDER BY ".implode(', ',$orderBySqlComponents);
		}
		$limitClauseParts = array();
		if( $sp->getLimit() !== null ) $limitClauseParts[] = "LIMIT ".$sp->getLimit();
		if( $sp->getSkip() != 0 ) $limitClauseParts[] = "OFFSET ".$sp->getSkip();
		if( $limitClauseParts ) $sql .= "\n".implode(' ',$limitClauseParts);
		
		return $sql;
	}
	
	protected function fetchRows( $sql, array $params ) {
		if( $sql == 'SELECT NOTHING' ) return array();
		$builder = new EarthIT_DBC_DoctrineStatementBuilder($this->registry->getDbAdapter());			
		$stmt = $builder->makeStatement($sql, $params);
		$stmt->execute();
		return $stmt->fetchAll();
	}
	
	protected function evaluateJohnTree(
		EarthIT_Schema_ResourceClass $rc,
		EarthIT_CMIPREST_SearchParameters $sp,
		array $johns,
		array $branches,
		$path, array &$results
	) {
		$params = array();
		$rootSql = $this->buildSearchSql( $rc, $sp, $params );
		if( $rootSql == 'SELECT NOTHING' ) $results[$path] = array();
		
		if( count($johns) == 0 ) {
			$sql = $rootSql;
		} else {
			$aliasNum = 0;
			$rootAlias = 'a'.($aliasNum++);
			$originAlias = $rootAlias;
			$joins = array();
			foreach( $johns as $j ) {
				$originRc = $j->originResourceClass;
				$targetRc = $j->targetResourceClass;
				$targetAlias = 'a'.($aliasNum++);
				$joinConditions = array();
				for( $li=0; $li<count($j->originLinkFields); ++$li ) {
					$originColumnParam = EarthIT_DBC_ParameterUtil::newParamName('originColumn');
					$targetColumnParam = EarthIT_DBC_ParameterUtil::newParamName('targetColumn');
					$params[$originColumnParam] = new EarthIT_DBC_SQLIdentifier($this->fieldDbName($originRc, $j->originLinkFields[$li]));
					$params[$targetColumnParam] = new EarthIT_DBC_SQLIdentifier($this->fieldDbName($targetRc, $j->targetLinkFields[$li]));
					$joinConditions[] = "{$targetAlias}.{{$targetColumnParam}} = {$originAlias}.{{$originColumnParam}}";
				}
				$targetTableParam = EarthIT_DBC_ParameterUtil::newParamName('targetTable');
				$params[$targetTableParam] = $this->rcTableExpression($targetRc);
				$joins[] = "JOIN {{$targetTableParam}} AS {$targetAlias} ON ".implode(' AND ',$joinConditions);
				$originAlias = $targetAlias;
			}
			$sql = "SELECT {$targetAlias}.* FROM (\n".
				"\t".str_replace("\n","\n\t",trim($rootSql))."\n".
				") AS {$rootAlias}\n".implode("\n",$joins);
		}
		
		$results[$path] = $this->fetchRows($sql, $params);
			
		foreach( $branches as $k=>$johnTreeNode ) {
			$newJohns = $johns;
			$newJohns[] = $johnTreeNode->getJohn();
			$this->evaluateJohnTree( $rc, $sp, $newJohns, $johnTreeNode->branches, $path.".".$k, $results );
		}
	}
	
	protected function collectJohns( $branches, $prefix, array $johns=array(), array &$paths=array() ) {
		$paths[$prefix] = $johns;
		foreach( $branches as $k=>$johnTreeNode ) {
			$johns2 = $johns;
			$johns2[] = $johnTreeNode->getJohn();
			$this->collectJohns( $johnTreeNode->getBranches(), $prefix.'.'.$k, $johns2, $paths );
		}
		return $paths;
	}
	
	/**
	 * Convert the given rows from DB to REST format according to the
	 * specified resource class and, if userId not null, ensure that
	 * they are visible to the user.
	 */
	protected function _q45( EarthIT_Schema_ResourceClass $rc, array $rows, $userId=null ) {
		$restObjects = array();
		foreach( $rows as $row ) {
			if( $userId !== null ) {
				$iitem = $this->dbObjectToInternal($rc, $row);
				if( !$this->postAuthorizeSearchResult($userId, $rc, $item, $authorizationExplanation) ) {
					throw new EarthIT_CMIPREST_ActionUnauthorized($act, $authorizationExplanation);
				}					
			}
			$restObjects[] = $this->dbObjectToRest($rc, $row);
		}
		return $restObjects;
	}
	
	protected function doSearchAction( EarthIT_CMIPREST_UserAction_SearchAction $act, $preAuth, $preAuthExplanation ) {
		$rc = $act->getResourceClass();
		$sp = $act->getSearchParameters();
		$queryParams = array();
		//$querySql = $this->buildSearchSql( $rc, $sp, array(), $queryParams );
		//if( $querySql == '' ) return array();
		//$this->fetchRows( $querySql, $queryParams );
		$relevantRows = array();
		$this->evaluateJohnTree( $rc, $sp, array(), $act->getJohnBranches(), 'root', $relevantRows );
		$johnCollections = $this->collectJohns( $act->getJohnBranches(), 'root' );
		
		$postAuthUserId = $preAuth ? null : $act->getUserId();
		
		$relevantRestObjects = array();
		foreach( $johnCollections as $path => $johns ) {
			$targetRc = count($johns) == 0 ? $rc : $johns[count($johns)-1]->targetResourceClass;
			$relevantRestObjects[$path] = $this->_q45( $targetRc, $relevantRows[$path], $postAuthUserId );
		}
		
		$assembled = array();
		
		// Assemble!
		foreach( $johnCollections as $path => $johns ) {
			$pathParts = explode('.',$path);
			if( count($pathParts) == 1 ) {
				foreach( $relevantRestObjects[$path] as $k=>$obj ) {
					$assembled[$k] =& $obj;
				}
			} else {
				$lastPathPart = $pathParts[count($pathParts)-1];
				$originPath = implode('.',array_slice($pathParts,0,count($pathParts)-1));
				$j = $johns[count($johns)-1];
				$plural = $j->targetIsPlural();
				$originRc = $j->originResourceClass;
				$targetRc = $j->targetResourceClass;
				$matchFields = array(); // target field rest name => origin field rest name
				for( $li=0; $li<count($j->originLinkFields); ++$li ) {
					$targetFieldName = $this->fieldRestName($targetRc, $j->targetLinkFields[$li]);
					$originFieldName = $this->fieldRestName($originRc, $j->originLinkFields[$li]);
					$matchFields[$targetFieldName] = $originFieldName;
				}
				foreach( $relevantRestObjects[$originPath] as $ok=>$ov ) {
					$relations = array();
					foreach( $relevantRestObjects[$path] as $tk=>$tv ) {
						$matches = true;
						foreach( $matchFields as $trf=>$orf ) {
							if( $tv[$trf] != $ov[$orf] ) $matches = false;
						}
						if( $matches ) {
							$relations[] =& $relevantRestObjects[$path][$tk];
						}
					}
					$relevantRestObjects[$originPath][$ok][$lastPathPart] = $plural ?
						$relations :
						(count($relations) == 0 ? null : $relations[0]);
				}
			}
		}
		
		return $relevantRestObjects['root'];
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
			return $this->doSearchAction($act, $preAuth, $authorizationExplanation);
		}
		
		if( $preAuth !== true ) {
			throw new Exception("preAuthorizeAction should only return true or false for non-search actions, but it returned ".var_export($auth,true));
		}
		
		// Otherwise it's A-Okay!
		
		if( $act instanceof EarthIT_CMIPREST_UserAction_GetItemAction ) {
			// Translate to a search action and take the first result
			$searchAct = new EarthIT_CMIPREST_UserAction_SearchAction(
				$act->getUserId(), $act->getResourceClass(),
				$this->itemIdToSearchParameters($act->getResourceClass(), $act->getItemId()),
				$act->getJohnBranches()
			);
			$results = $this->doAction($searchAct);
			if( count($results) == 0 ) return null;
			if( count($results) == 1 ) return $results[0];
			throw new Exception("Multiple records found with ID = '".$act->getItemId()."'");
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
			return Nife_Util::httpResponse( $result === null ? 404 : 200, new EarthIT_JSON_PrettyPrintedJSONBlob($result), 'application/json' );
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
