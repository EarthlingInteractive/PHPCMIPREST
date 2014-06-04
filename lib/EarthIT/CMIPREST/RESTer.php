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

class EarthIT_CMIPREST_RESTer
{
	/**
	 * Returned to indicate that an action succeeded but that there is
	 * no meaningful data to return
	 */
	const SUCCESS = "You're Winner!";
	
	//// Component management
	
	protected $storage;
	protected $schema;
	
	public function __construct( $params ) {
		if( $params instanceof EarthIT_Registry ) {
			$registry = $params;
			$params = array(
				'dbAdapter' => $registry->getDbAdapter(),
				'dbNamer' => $registry->getDbNamer(),
				'schema' => $registry->getSchema(),
			);
		}
		if( is_array($params) ) {
			$params = array_merge(array(
				'storage' => null,
				'dbAdapter' => null,
				'dbNamer' => null,
				'schema' => null,
			), $params);
		} else {
			throw new Exception("Parameters to RESTer constructor must be an array or an EarthIT_Registry");
		}
		
		if( ($this->storage = $params['storage']) ) {
			// Okay!
		} else if( $params['dbAdapter'] and $params['schema'] and $params['dbNamer'] ) {
			$this->storage = new EarthIT_CMIPREST_PostgresStorage($params['dbAdapter'], $params['schema'], $params['dbNamer']);
		} else {
			throw new Exception("No storage or (dbAdapter + schema + dbNamer) specified.");
		}
		
		if( ($this->schema = $params['schema']) ) {
			// Okay!
		} else {
			throw new Exception("No schema specified.");
		}
	}
		
	//// Field conversion
	
	// TODO: Make configurable
	protected function fieldRestName( EarthIT_Schema_ResourceClass $rc, EarthIT_Schema_Field $f ) {
		// When emitting JSON, format names as the JS does
		return EarthIT_Schema_WordUtil::toCamelCase($f->getName());
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
	
	protected function internalObjectToRest( EarthIT_Schema_ResourceClass $rc, array $fieldValues ) {
		$result = array();
		foreach( $rc->getFields() as $f ) {
			if( isset($fieldValues[$f->getName()]) ) {
				$result[$this->fieldRestName($rc, $f)] = $fieldValues[$f->getName()];
			}
		}
		return $result;
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
		$fieldsByName = $rc->getFields();
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
			if( isset($fieldsByName[$cv]) ) {
				$field = $fieldsByName[$cv];
			} else if( isset($fieldsByRestName[$cv]) ) {
				$field = $fieldsByRestName[$cv];
			} else {
				throw new Exception("Unknown field in orderBy: '$cv'");
			}
			$orderByComponents[] = new EarthIT_CMIPREST_OrderByComponent($field, $ascending);
		}
		return $orderByComponents;
	}
	
	/**
	 * a.b.c.d -> { a: { b: { c: { d: {} } } } }
	 */
	protected static function parsePathToTree( $path, array &$into ) {
		if( $path === '' ) return;
		if( is_string($path) ) $path = explode('.', $path);
		if( count($path) == 0 ) return;
		
		if( !isset($into[$path[0]]) ) {
			$into[$path[0]] = array();
		}
		
		self::parsePathToTree( array_slice($path, 1), $into[$path[0]] );
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
				$targetRc = $this->schema->getResourceClass($ref->getTargetClassName());
				return new EarthIT_CMIPREST_John(
					$originRc, self::getFields($originRc, $ref->getOriginFieldNames()),
					$targetRc, self::getFields($targetRc, $ref->getTargetFieldNames()),
					false
				);
			}
		}
		
		/* TODO:
		 * Eventually we should be able to define inverse relationship
		 * names and plurality in the schema, possibly falling back on
		 * the method of finding them following this comment.
		 */
		
		/*
		 * Try to find a reference from a class 'X' where plural(X) = the requested link,
		 * and return a plural John of the inverse of that reference.
		 */
		$inverseJohns = array();
		foreach( $this->schema->getResourceClasses() as $targetRc ) {
			$pluralRestName = EarthIT_Schema_WordUtil::toCamelCase(EarthIT_Schema_WordUtil::pluralize($targetRc->getName()));
			if( $pluralRestName == $linkRestName ) {
				foreach( $targetRc->getReferences() as $inverseRef ) {
					if( $inverseRef->getTargetClassName() == $originRc->getName() ) {
						$inverseJohns[] = new EarthIT_CMIPREST_John(
							$originRc, self::getFields($originRc, $inverseRef->getTargetFieldNames()),
							$targetRc, self::getFields($targetRc, $inverseRef->getOriginFieldNames()),
							true // Assuming plural for now.
						);
					}
				}
			}
		}
		if( count($inverseJohns) == 1 ) {
			return $inverseJohns[0];
		} else if( count($inverseJohns) > 1 ) {
			$list = array();
			foreach( $inverseJohns as $ij ) {
				$originFieldNames = array();
				foreach( $ij->targetLinkFields as $fn=>$f ) {
					$originFieldNames[] = $f->getName();
				}
				$list[] = implode(', ',$originFieldNames);
			}
			// Alternatively, we could just include all of them.
			throw new Exception(
				"The link '$linkRestName' from ".$originRc->getName()." is ambiguous.\n".
				"It could indicate a link based on any of: ".implode('; ',$list)
			);
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
	
	protected function parseWithsToJohnBranches( EarthIT_Schema_ResourceClass $originRc, $withs ) {
		if( is_scalar($withs) ) $withs = explode(',',$withs);
		if( !is_array($withs) ) throw new Exception("withs parameter must be an array or comma-delimited string.");
		$pathTree = array();
		foreach( $withs as $segment ) self::parsePathToTree(explode('.',$segment), $pathTree);
		return $this->withsToJohnBranches( $pathTree, $originRc );
	}
	
	protected function cmipRequestToUserAction( EarthIT_CMIPREST_CMIPRESTRequest $crr ) {
		$userId = $crr->getUserId();
		$resourceClass = $this->schema->getResourceClass( EarthIT_Schema_WordUtil::depluralize($crr->getResourceCollectionName()) );
		
		if( !$resourceClass->hasRestService() ) {
			throw new EarthIT_CMIPREST_ResourceNotExposedViaService("'".$resourceClass->getName()."' records are not exposed via services");
		}
		
		switch( $crr->getMethod() ) {
		case 'GET': case 'HEAD':
			$johnBranches = array();
			foreach( $crr->getResultModifiers() as $k=>$v ) {
				if( $k === 'with' ) {
					$johnBranches = $this->parseWithsToJohnBranches($resourceClass, $v);
				} else {
					throw new Exception("Unrecognized result modifier: '$k'");
				}
			}
			
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
				throw new Exception("You ust include item ID when DELETEing");
			}
			return new EarthIT_CMIPREST_UserAction_DeleteItemAction( $userId, $resourceClass, $crr->getResourceInstanceId() );
		default:
			throw new Exception("Unrecognized method, '".$crr->getMethod()."'");
		}
	}
	
	protected function itemIdToSearchParameters( EarthIT_Schema_ResourceClass $rc, $id ) {
		$fieldValues = EarthIT_CMIPREST_Util::idToFieldValues( $rc, $id );
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
	protected function _q45( EarthIT_Schema_ResourceClass $rc, array $items, $userId=null ) {
		$restObjects = array();
		foreach( $items as $item ) {
			if( $userId !== null ) {
				if( !$this->postAuthorizeSearchResult($userId, $rc, $item, $authorizationExplanation) ) {
					throw new EarthIT_CMIPREST_ActionUnauthorized($act, $authorizationExplanation);
				}					
			}
			$restObjects[] = $this->internalObjectToRest($rc, $item);
		}
		return $restObjects;
	}
	
	protected function doSearchAction( EarthIT_CMIPREST_UserAction_SearchAction $act, $preAuth, $preAuthExplanation ) {
		$rc = $act->getResourceClass();
		$sp = $act->getSearchParameters();
		$queryParams = array();
		$relevantObjects = $this->storage->search( $rc, $sp, $act->getJohnBranches() );
		$johnCollections = $this->collectJohns( $act->getJohnBranches(), 'root' );
		
		$postAuthUserId = $preAuth ? null : $act->getUserId();
		
		$relevantRestObjects = array();
		foreach( $johnCollections as $path => $johns ) {
			$targetRc = count($johns) == 0 ? $rc : $johns[count($johns)-1]->targetResourceClass;
			$relevantRestObjects[$path] = $this->_q45( $targetRc, $relevantObjects[$path], $postAuthUserId );
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
					if( $plural ) {
						$relevantRestObjects[$originPath][$ok][$lastPathPart] = $relations;
					} else if( count($relations) == 0 ) {
						$relevantRestObjects[$originPath][$ok][$lastPathPart] = null;
					} else {
						$relevantRestObjects[$originPath][$ok][$lastPathPart] =& $relations[0];
					}
				}
			}
		}
		
		return $relevantRestObjects['root'];
	}
	
	protected function getRestObject( EarthIT_Schema_ResourceClass $resourceClass, $itemId ) {
		$obj = $this->storage->getItem($resourceClass, $itemId);
		return $obj === null ? null : $this->dbObjectToRest($resourceClass, $obj);
	}
	
	/**
	 * Perform a PUT (merge = false) or PATCH (merge = true) action.
	 */
	protected function doPatchLikeAction( EarthIT_CMIPREST_UserAction $act, $merge ) {
		// TODO: Make it work even if the record does not already exist
		
		$itemId = $act->getItemId();
		$resourceClass = $act->getResourceClass();
		$idFieldValues = self::idToFieldValues( $resourceClass, $itemId );
		$internalValues = self::mergeEnsuringNoContradictions( $idFieldValues, $act->getItemData() );
		if( !$merge ) {
			// Set other field values to their defaults.
			// Assuming null for now...
			foreach( $resourceClass->getFields() as $fieldName => $field ) {
				if( !isset($internalValues[$fieldName]) ) {
					$internalValues[$fieldName] = null;
				}
			}
		}
		
		$params = array('table' => $this->rcTableExpression($resourceClass));
		$conditions = self::encodeColumnValuePairs($this->internalObjectToDb($resourceClass, $idFieldValues ), $params);
		$sets       = self::encodeColumnValuePairs($this->internalObjectToDb($resourceClass, $internalValues), $params);
		$this->doQuery(
			"UPDATE {table} SET\n".
			"\t".implode(",\n\t", $sets).
			"WHERE ".implode("\n  AND ",$conditions),
			$params
		);
		return $this->getRestObject( $resourceClass, $itemId );
	}
	
	/**
	 * Ensure that the given action is structurally valid so that
	 * assumptions made while authorizing hold true.
	 * Will throw an exception otherwise.
	 */
	protected function validateAction( EarthIT_CMIPREST_UserAction $act ) {
		// TODO
	}
	
	/**
	 * Result will be a JSON array in REST form.
	 * Errors will be thrown as exceptions.
	 */
	public function doAction( EarthIT_CMIPREST_UserAction $act ) {
		$this->validateAction($act);
		
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
			$rc = $act->getResourceClass();
			return $this->internalObjectToRest( $rc, $this->storage->postItem($rc, $act->getItemData()) );
		} else if( $act instanceof EarthIT_CMIPREST_UserAction_PutItemAction ) {
			$rc = $act->getResourceClass();
			return $this->internalObjectToRest( $rc, $this->storage->putItem($rc, $act->getItemId(), $act->getItemData()) );
		} else if( $act instanceof EarthIT_CMIPREST_UserAction_PatchItemAction ) {
			$rc = $act->getResourceClass();
			return $this->internalObjectToRest( $rc, $this->storage->patchItem($rc, $act->getItemId(), $act->getItemData()) );
		} else if( $act instanceof EarthIT_CMIPREST_UserAction_DeleteItemAction ) {
			$resourceClass = $act->getResourceClass();
			$params = array('table' => $this->rcTableExpression( $resourceClass ));
			$conditions = self::encodeColumnValuePairs($this->itemIdToColumnValues($resourceClass, $act->getItemId()), $params);
			$this->doQuery(
				"DELETE FROM {table}\n".
				"WHERE ".implode("\n  AND ",$conditions),
				$params
			);
			return self::SUCCESS;
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
	
	protected static function normalResponse( $result ) {
		if( $result === null ) {
			return Nife_Util::httpResponse( 404, new EarthIT_JSON_PrettyPrintedJSONBlob($result), 'application/json' );
		} else if( $result === self::SUCCESS ) {
			return Nife_Util::httpResponse( 201, '' );
		} else {
			return Nife_Util::httpResponse( 200, new EarthIT_JSON_PrettyPrintedJSONBlob($result), 'application/json' );
		}
	}

	public function handle( EarthIT_CMIPREST_CMIPRESTRequest $crr ) {
		// TODO: Put exception -> response mapping in its own function
		
		try {
			$act = $this->cmipRequestToUserAction($crr);
			$result = $this->doAction($act);
			return self::normalResponse($result);
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
