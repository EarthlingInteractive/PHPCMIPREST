<?php

/*
 * TODO:
 * - Make REST field namer configurable
 *   or make it part of the action
 * - Comment functions better
 * - Split into 2 or 3 independent objects
 *   CMIPRESTRequest -> UserAction translator
 *   UserAction -> I/O
 */

class EarthIT_CMIPREST_RESTer
{
	/**
	 * Returned to indicate that an action succeeded but that there is
	 * no meaningful data to return.
	 * These will get translated to HTTP 204 responses.
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
	
	protected function internalValueToRest( EarthIT_Schema_DataType $dt, $v ) {
		return $v;
	}
	
	//// Object conversion
	
	protected function restObjectToInternal( EarthIT_Schema_ResourceClass $rc, array $restObj ) {
		$internal = array();
		foreach( $rc->getFields() as $field ) {
			$frn = $this->fieldRestName( $rc, $field );
			if( array_key_exists($frn, $restObj) ) {
				$internal[$field->getName()] =
					$this->restValueToInternal($field->getType(), $restObj[$frn]);
			}
		}
		return $internal;
	}
	
	protected function internalObjectToRest( EarthIT_Schema_ResourceClass $rc, array $fieldValues ) {
		$result = array();
		foreach( EarthIT_CMIPREST_Util::restReturnableFields($rc) as $field ) {
			if( array_key_exists($field->getName(), $fieldValues) ) {
				$result[$this->fieldRestName($rc, $field)] =
					$this->internalValueToRest($field->getType(), $fieldValues[$field->getName()]);
			}
		}
		return $result;
	}
	
	////
	
	protected function getFieldsByRestName( EarthIT_Schema_ResourceClass $rc ) {
		$fbrn = array();
		foreach( $rc->getFields() as $f ) {
			$fbrn[$this->fieldRestName($rc, $f)] = $f;
		}
		return $fbrn;
	}
	
	//// Action conversion
	
	protected static function parseValue( $v, EarthIT_Schema_DataType $fieldType ) {
		switch( $fieldType->getPhpTypeName() ) {
		case 'string': return $v;
		case 'int': return (int)$v;
		case 'float': return (float)$v;
		case 'bool':
			switch( $v ) {
			case '1': case 'true' : return true;
			case '0': case 'false': return false;
			default:
				throw new Exception("Don't know how to parse \"$v\" as a boolean value (try using 'true', 'false', '1', or '0').");
			}
		default:
			throw new Exception("Don't know how to parse \"$v\" as a ".$fieldType->getName());
		}
	}
	
	protected static function parseFieldMatcher( $v, EarthIT_Schema_DataType $fieldType ) {
		$colonIdx = strpos($v, ':');
		if( $colonIdx === false ) {
			return (strpos($v, '*') === false) ?
				new EarthIT_CMIPREST_FieldMatcher_Equal(self::parseValue($v, $fieldType)) :
				new EarthIT_CMIPREST_FieldMatcher_Like($v);
		} else {
			$scheme = substr($v, 0, $colonIdx);
			$pattern = substr($v, $colonIdx+1) ?: ''; // Because substr('xyz',3) returns false. #phpwtf
			if( $scheme == 'in' ) {
				$vals = array();
				if( $pattern !== '' ) foreach( explode(',',$pattern) as $p ) {
					$vals[] = self::parseValue($p, $fieldType);
				}
				return new EarthIT_CMIPREST_FieldMatcher_In($vals);
			} else if( $scheme == 'like' ) {
				return new EarthIT_CMIPREST_FieldMatcher_Like($pattern);
			}
			$value = self::parseValue($pattern, $fieldType);
			switch( $scheme ) {
			case 'eq': return new EarthIT_CMIPREST_FieldMatcher_Equal($value);
			case 'ne': return new EarthIT_CMIPREST_FieldMatcher_NotEqual($value);
			case 'gt': return new EarthIT_CMIPREST_FieldMatcher_Greater($value);
			case 'ge': return new EarthIT_CMIPREST_FieldMatcher_GreaterOrEqual($value);
			case 'lt': return new EarthIT_CMIPREST_FieldMatcher_Lesser($value);
			case 'le': return new EarthIT_CMIPREST_FieldMatcher_LesserOrEqual($value);
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
	
	// TODO: Delegate to utility class.  Remove for 1.0.0.
	private function findJohnByRestName( EarthIT_Schema_ResourceClass $originRc, $linkRestName ) {
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
			$pluralRestName = EarthIT_Schema_WordUtil::toCamelCase(
				$targetRc->getFirstPropertyValue("http://ns.earthit.com/CMIPREST/collectionName") ?:
				EarthIT_Schema_WordUtil::pluralize($targetRc->getName())
			);
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
	
	private function _withsToJohnBranches( array $withs, EarthIT_Schema_ResourceClass $originRc ) {
		$branches = array();
		foreach( $withs as $k=>$subWiths ) {
			$john = $this->findJohnByRestName( $originRc, $k );
			$branches[$k] = new EarthIT_CMIPREST_JohnTreeNode(
				$john,
				$this->_withsToJohnBranches( $subWiths, $john->targetResourceClass )
			);
		}
		return $branches;
	}
	
	/**
	 * @api
	 */
	public function withsToJohnBranches( EarthIT_Schema_ResourceClass $originRc, $withs ) {
		if( is_scalar($withs) ) $withs = explode(',',$withs);
		if( !is_array($withs) ) throw new Exception("withs parameter must be an array or comma-delimited string.");
		$pathTree = array();
		foreach( $withs as $segment ) self::parsePathToTree(explode('.',$segment), $pathTree);
		return $this->_withsToJohnBranches( $pathTree, $originRc );
	}
	
	/**
	 * @return EarthIT_CMIPREST_UserAction
	 * @api
	 * @intended-to-be-overridden-by-application
	 */
	public function cmipRequestToUserAction( EarthIT_CMIPREST_CMIPRESTRequest $crr ) {
		if( ($propName = $crr->getResourcePropertyName()) !== null ) {
			throw new Exception("Unrecognized resource property, '$propName'");
		}
		
		$userId = $crr->getUserId();
		$resourceClass = EarthIT_CMIPREST_Util::getResourceClassByCollectionName($this->schema, $crr->getResourceCollectionName());
		
		if( !$resourceClass->hasRestService() ) {
			throw new EarthIT_CMIPREST_ResourceNotExposedViaService("'".$resourceClass->getName()."' records are not exposed via services");
		}
		
		switch( $crr->getMethod() ) {
		case 'GET': case 'HEAD':
			$johnBranches = array();
			foreach( $crr->getResultModifiers() as $k=>$v ) {
				if( $k === 'with' ) {
					$johnBranches = $this->withsToJohnBranches($resourceClass, $v);
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
						$fieldName = $fieldRestToInternalNames[$k];
						$fieldType = $fields[$fieldName]->getType();
						$fieldMatchers[$fieldName] = self::parseFieldMatcher($v, $fieldType);
					}
				}
				$sp = new EarthIT_CMIPREST_SearchParameters( $fieldMatchers, $orderBy, $skip, $limit );
				return new EarthIT_CMIPREST_UserAction_SearchAction( $userId, $resourceClass, $sp, $johnBranches );
			}
		case 'POST':
			if( $crr->getResourceInstanceId() !== null ) {
				throw new Exception("You may not include item ID when POSTing");
			}
			$data = $crr->getContent();
			
			// If all keys are sequential integers (this includes the
			// case when an empty list is posted), then a list of items
			// is being posted.
			// Otherwise, a single item is being posted and will be returned.
			// The multi-item case should be considered the normal one;
			// auto-detecting the single-item case is for backward-compatibility only.
			
			$isSingleItemPost = false;
			$len = count($data);
			for( $i=0; $i<$len; ++$i ) {
				if( !array_key_exists($i, $data) ) {
					$isSingleItemPost = true;
					break;
				}
			}
			
			if( $isSingleItemPost ) {
				return new EarthIT_CMIPREST_UserAction_PostItemAction(
					$userId, $resourceClass,
					$this->restObjectToInternal($resourceClass, $data)
				);
			} else {
				$items = array();
				foreach( $data as $dat ) {
					$items[] = $this->restObjectToInternal($resourceClass, $dat);
				}
				return EarthIT_CMIPREST_UserActions::multiPost($userId, $resourceClass, $items);
			}
		case 'PUT':
			if( $crr->getResourceInstanceId() === null ) {
				throw new Exception("You ust include item ID when PUTing");
			}
			return new EarthIT_CMIPREST_UserAction_PutItemAction(
				$userId, $resourceClass, $crr->getResourceInstanceId(),
				$this->restObjectToInternal($resourceClass, $crr->getContent())
			);
		case 'PATCH':
			if( $crr->getResourceInstanceId() === null ) {
				throw new Exception("You ust include item ID when PATCHing");
			}
			return new EarthIT_CMIPREST_UserAction_PatchItemAction(
				$userId, $resourceClass, $crr->getResourceInstanceId(),
				$this->restObjectToInternal($resourceClass, $crr->getContent())
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
	
	//// Action validation/authorization
	
	/**
	 * Ensure that the given action is structurally valid so that
	 * assumptions made while authorizing hold true.
	 * Will throw an exception otherwise.
	 * 
	 * @intended-to-be-overridden-by-application
	 */
	protected function validateSimpleAction( EarthIT_CMIPREST_UserAction $act ) {
		// TODO
	}
	
	/**
	 * Determine if an action is allowed before actually doing it.
	 * For search actions, this may return null to indicate that
	 * authorization requires the actual search results, which will be passed
	 * to postAuthorizeSearchResult to determine is they are allowed.
	 * 
	 * @intended-to-be-overridden-by-application
	 */
	protected function preAuthorizeSimpleAction( EarthIT_CMIPREST_UserAction $act, array &$explanation ) {
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
	
	/**
	 * @intended-to-be-overridden-by-application
	 */
	protected function postAuthorizeSearchResult( $userId, EarthIT_Schema_ResourceClass $rc, array $itemData, array &$explanation ) {
		$explanation[] = "Unauthorized by default.";
		return false;
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
	
	// TODO:
	// Sometimes projects will want to extend
	// the set of actions and how they are implemented.
	// It might be nice if doAction were refactored to delegate
	// to separate validateSimpleAction, preAuthorizeSimpleAction, actuallyDoAction methods
	// (or somesuch) so that they could be more easily overridden.
	
	/**
	 * Result will be either a Nife_HTTP_Response, RESTer::SUCCESS, or a JSON array in REST form.
	 * Errors will be thrown as exceptions.
	 * @client-overridable
	 * @return Nife_HTTP_Response|RESTer::SUCCESS|array
	 */
	protected function doSimpleAction( EarthIT_CMIPREST_UserAction $act ) {
		$this->validateSimpleAction($act);
		
		$authorizationExplanation = array();
		$preAuth = $this->preAuthorizeSimpleAction($act, $authorizationExplanation);
		
		if( $preAuth === false ) {
			throw new EarthIT_CMIPREST_ActionUnauthorized($act, $authorizationExplanation);
		}
		
		if( $act instanceof EarthIT_CMIPREST_UserAction_SearchAction ) {
			return $this->doSearchAction($act, $preAuth, $authorizationExplanation);
		}
		
		if( $preAuth !== true ) {
			throw new Exception("preAuthorizeSimpleAction should only return true or false for non-search actions, but it returned ".var_export($auth,true));
		}
		
		// Otherwise it's A-Okay!
		
		if( $act instanceof EarthIT_CMIPREST_UserAction_GetItemAction ) {
			// Translate to a search action and take the first result
			$searchAct = new EarthIT_CMIPREST_UserAction_SearchAction(
				$act->getUserId(), $act->getResourceClass(),
				EarthIT_CMIPREST_Util::itemIdToSearchParameters($act->getResourceClass(), $act->getItemId()),
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
			$this->storage->deleteItem($act->getResourceClass(), $act->getItemId());
			return self::SUCCESS;
		} else {
			// TODO
			throw new Exception(get_class($act)." not supported");
		}
	}
	
	/**
	 * @return Nife_HTTP_Response|RESTer::SUCCESS|array
	 */
	protected function doCompoundAction( EarthIT_CMIPREST_UserAction_CompoundAction $act ) {
		// For now just doing all actions in order.
		// If one fails to validate, earlier actions will still have been run.
		$subActionResults = array();
		foreach( $act->getActions() as $k=>$subAct ) {
			$subActionResults[$k] = $this->doAction($subAct);
		}
		$context = array('action results'=>$subActionResults);
		return $act->getResultExpression()->evaluate($context);
	}
	
	/**
	 * @api
	 * @return Nife_HTTP_Response|RESTer::SUCCESS|array
	 */
	public function doAction( EarthIT_CMIPREST_UserAction $act ) {
		// TODO: Validate and preAuthorize before doing anything
		// instead of validating/authorizing each action as it is run.
		if( $act instanceof EarthIT_CMIPREST_UserAction_CompoundAction ) {
			return $this->doCompoundAction($act);
		} else {
			return $this->doSimpleAction($act);
		}
	}
	
	protected function normalResponse( $result ) {
		if( $result === null ) {
			return Nife_Util::httpResponse( 404, new EarthIT_JSON_PrettyPrintedJSONBlob($result), 'application/json' );
		} else if( $result instanceof Nife_HTTP_Response ) {
			return $result;
		} else if( $result === self::SUCCESS ) {
			return Nife_Util::httpResponse( 204, '' );
		} else {
			return Nife_Util::httpResponse( 200, new EarthIT_JSON_PrettyPrintedJSONBlob($result), 'application/json' );
		}
	}
	
	/**
	 * @api
	 * @return Nife_HTTP_Response
	 */
	public function handle( EarthIT_CMIPREST_CMIPRESTRequest $crr ) {
		// TODO: Put exception -> response mapping in its own function
		
		try {
			$act = $this->cmipRequestToUserAction($crr);
			$result = $this->doAction($act);
			return $this->normalResponse($result);
		} catch( EarthIT_CMIPREST_ActionUnauthorized $un ) {
			$status = $act->getUserId() === null ? 401 : 403;
			return EarthIT_CMIPREST_Util::singleErrorResponse( $status, $un->getAction()->getActionDescription(), $un->getNotes() );
		} catch( EarthIT_CMIPREST_ActionInvalid $un ) {
			return EarthIT_CMIPREST_Util::multiErrorResponse( 409, $un->getErrorDetails() );
		} catch( EarthIT_Schema_NoSuchResourceClass $un ) {
			return EarthIT_CMIPREST_Util::singleErrorResponse( 404, $un->getMessage() );
		} catch( EarthIT_CMIPREST_ResourceNotExposedViaService $un ) {
			return EarthIT_CMIPREST_Util::singleErrorResponse( 404, $un->getMessage() );
		}
	}
}
