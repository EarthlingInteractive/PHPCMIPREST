<?php

use EarthIT_CMIPREST_RequestParser_Util AS RPU;

/*
 * TODO:
 * - Remove cmiRequestToResourceAction
 * - Comment functions better
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
	protected $keyByIds; // TODO: Remove when CMIP request parsing extracted
	protected $authorizer;
	
	public function __construct( $params ) {
		if( !is_array($params) ) {
			throw new Exception("Parameters to RESTer constructor must be an array");
		}
		
		$params += array(
			'storage' => null,
			'dbAdapter' => null,
			'dbNamer' => null,
			'schema' => null,
			'keyByIds' => false,
			'authorizer' => new EarthIT_CMIPREST_RESTActionAuthorizer_DefaultRESTActionAuthorizer()
		);
		
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
		
		$this->keyByIds = $params['keyByIds'];
		$this->authorizer = $params['authorizer'];
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
	
	/** @deprecated, as there may be multiple REST forms.  The reuest parser should do this */
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
	
	/** @deprecated */
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
	
	/** @deprecated */
	protected function getFieldsByRestName( EarthIT_Schema_ResourceClass $rc ) {
		$fbrn = array();
		foreach( $rc->getFields() as $f ) {
			$fbrn[$this->fieldRestName($rc, $f)] = $f;
		}
		return $fbrn;
	}
	
	//// Action conversion
	
	/**
	 * a.b.c.d -> { a: { b: { c: { d: {} } } } }
	 * @deprecated
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
	
	/** @deprecated */
	protected static function getFields( EarthIT_Schema_ResourceClass $rc, array $fieldNames ) {
		$f = $rc->getFields();
		$fields = array();
		foreach( $fieldNames as $fn ) $fields[] = $f[$fn];
		return $fields;
	}
	
	// TODO: Delegate to utility class.  Remove for 1.0.0.
	/** @deprecated */
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
			$targetRcPluralRestName = EarthIT_Schema_WordUtil::toCamelCase(
				$targetRc->getFirstPropertyValue(EarthIT_CMIPREST_NS::COLLECTION_NAME) ?:
				EarthIT_Schema_WordUtil::pluralize($targetRc->getName())
			);
			foreach( $targetRc->getReferences() as $inverseRef ) {
				if( $inverseRef->getTargetClassName() == $originRc->getName() ) {
					$refInverseName = $inverseRef->getFirstPropertyValue(EarthIT_CMIPREST_NS::INVERSE_NAME);
					$refPluralInverseName = $inverseRef->getFirstPropertyValue(EarthIT_CMIPREST_NS::INVERSE_COLLECTION_NAME);
					if( $refPluralInverseName === null and $refInverseName ) {
						$refPluralInverseName = EarthIT_Schema_WordUtil::pluralize($refInverseName);
					}
					$refPluralInversRestName = $refPluralInverseName === null ? null :
						EarthIT_Schema_WordUtil::toCamelCase($refPluralInverseName);
					$pluralRestName = $refPluralInversRestName ?: $targetRcPluralRestName;
					// If an inverse name is specified for the reference,
					// it must be used instead of the class name.
					if( $pluralRestName == $linkRestName ) {
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
			return EarthIT_CMIPREST_Util::first($inverseJohns);
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
	
	//// Action validation/authorization
	
	/**
	 * Ensure that the given action is structurally valid so that
	 * assumptions made while authorizing hold true.
	 * Will throw an exception otherwise.
	 * 
	 * @overridable
	 */
	protected function validateSimpleAction( EarthIT_CMIPREST_RESTAction $act ) {
		if( $act instanceof EarthIT_CMIPREST_RESTAction_ResourceAction ) {
		} else if( $act instanceof EarthIT_CMIPREST_RESTAction_InvalidAction ) {
			throw new EarthIT_CMIPREST_ActionInvalid($act, $act->getErrorDetails());
		} else {
			throw new EarthIT_CMIPREST_ActionInvalid($act, array(array(
				'class' => 'ClientError/ActionInvalid',
				'message' => 'Unrecognized action class: '.get_class($act)
			)));
		}
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
	
	protected function doSearchAction( EarthIT_CMIPREST_RESTAction_SearchAction $act, $ctx, $preAuth, $authorizationExplanation ) {
		$rc = $act->getResourceClass();
		$search = $act->getSearch();
		$queryParams = array();
		$relevantObjects = $this->storage->johnlySearchItems( $search, $act->getJohnBranches() );
		$johnCollections = $this->collectJohns( $act->getJohnBranches(), 'root' );
		
		// If we need to post-authorize, do it.
		if( $preAuth === EarthIT_CMIPREST_RESTActionAuthorizer::AUTHORIZED_IF_RESULTS_VISIBLE ) {
			foreach( $johnCollections as $path => $johns ) {
				// Figure out what resource class of items we got, here
				$targetRc = count($johns) == 0 ? $rc : $johns[count($johns)-1]->targetResourceClass;
				
				// Ensure that they're visisble
				if( !$this->authorizer->itemsVisible($relevantObjects[$path], $targetRc, $ctx, $authorizationExplanation) ) {
					throw new EarthIT_CMIPREST_ActionUnauthorized($act, $ctx, $authorizationExplanation);
				}
			}
		}
		
		return $act->getResultAssembler()->assembleResult(
			new EarthIT_CMIPREST_StorageResult($rc, $johnCollections, $relevantObjects));
	}
	
	/**
	 * Assemble results with no johns.  i.e. a simple collection of one type of objects
	 */
	protected function assembleSimpleResult($act, $rc, array $items) {
		$assembler = $act->getResultAssembler();
		if( $assembler === null ) {
			throw new Exception("No result assembler specified by ".get_class($act));
		}
		
		return $assembler->assembleResult(
			new EarthIT_CMIPREST_StorageResult($rc, array('root'=>array()), array('root'=>$items)) );
	}

	// TODO:
	// Sometimes projects will want to extend
	// the set of actions and how they are implemented.
	// It might be nice if doAction were refactored to delegate
	// to separate validateSimpleAction, preAuthorizeSimpleAction, actuallyDoAction methods
	// (or somesuch) so that they could be more easily overridden.
	//
	// Something like
	// $action = $this->actionValidator->validate($action) throws ActionInvalid
	// would allow validate to DWIM for cases that would otherwise not be valid
	// but that we want to allow by automatically 'fixing'
	
	/**
	 * Result will be either a Nife_HTTP_Response, RESTer::SUCCESS, or a JSON array in REST form.
	 * Errors will be thrown as exceptions.
	 * @overridable
	 * @return Nife_HTTP_Response|RESTer::SUCCESS|array
	 */
	protected function doSimpleAction( EarthIT_CMIPREST_RESTAction $act, $ctx ) {
		$this->validateSimpleAction($act);
		
		$rc = $act->getResourceClass();
		
		if( $act instanceof EarthIT_CMIPREST_RESTAction_GetItemAction ) {
			// Translate to a search action!
			$act = new EarthIT_CMIPREST_RESTAction_SearchAction(
				new EarthIT_Storage_Search($rc, EarthIT_Storage_ItemFilters::byId($act->getItemId(), $rc)),
				$act->getJohnBranches(),
				array(),
				$act->getResultAssembler()
			);
		}
		
		$authorizationExplanation = array();
		$preAuth = $this->authorizer->preAuthorizeSimpleAction($act, $ctx, $authorizationExplanation);
		
		if( $preAuth === false ) {
			throw new EarthIT_CMIPREST_ActionUnauthorized($act, $ctx, $authorizationExplanation);
		}
		
		if( $act instanceof EarthIT_CMIPREST_RESTAction_SearchAction ) {
			return $this->doSearchAction($act, $ctx, $preAuth, $authorizationExplanation);
		}
		
		if( $preAuth !== true ) {
			throw new Exception("preAuthorizeSimpleAction should only return true or false for non-search actions, but it returned ".var_export($preAuth,true));
		}
		
		// Otherwise it's A-Okay!
		
		if( $act instanceof EarthIT_CMIPREST_RESTAction_PostItemAction ) {
			$rc = $act->getResourceClass();
			return $this->assembleSimpleResult($act, $rc, array($this->storage->postItem($rc, $act->getItemData())));
		} else if( $act instanceof EarthIT_CMIPREST_RESTAction_PutItemAction ) {
			$rc = $act->getResourceClass();
			return $this->assembleSimpleResult($act, $rc, array($this->storage->putItem($rc, $act->getItemId(), $act->getItemData())));
		} else if( $act instanceof EarthIT_CMIPREST_RESTAction_PatchItemAction ) {
			$rc = $act->getResourceClass();
			return $this->assembleSimpleResult($act, $rc, array($this->storage->patchItem($rc, $act->getItemId(), $act->getItemData())));
		} else if( $act instanceof EarthIT_CMIPREST_RESTAction_DeleteItemAction ) {
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
	protected function doCompoundAction( EarthIT_CMIPREST_RESTAction_CompoundAction $act, $ctx ) {
		// For now just doing all actions in order.
		// If one fails to validate, earlier actions will still have been run.
		$subActionResults = array();
		foreach( $act->getActions() as $k=>$subAct ) {
			$subActionResults[$k] = $this->doAction($subAct, $ctx);
		}
		return $act->getResultExpression()->evaluate(array('action results'=>$subActionResults));
	}
	
	/**
	 * @api
	 * @return Nife_HTTP_Response|RESTer::SUCCESS|array
	 */
	public function doAction( EarthIT_CMIPREST_RESTAction $act, $ctx ) {
		// TODO: Validate and preAuthorize before doing anything
		// instead of validating/authorizing each action as it is run.
		if( $act instanceof EarthIT_CMIPREST_RESTAction_CompoundAction ) {
			return $this->doCompoundAction($act, $ctx);
		} else {
			return $this->doSimpleAction($act, $ctx);
		}
	}
	
	//// Create Nife responses from action results
		
	/**
	 * @api
	 * Does an action and wraps the response.
	 * Handy for unit testing.
	 */
	public function doActionAndGetHttpResponse( EarthIT_CMIPREST_RESTAction $act, $ctx ) {
		try {
			$rez = $this->doAction($act, $ctx);
			return $act->getResultAssembler()->assembledResultToHttpResponse($rez);
		} catch( Exception $e ) {
			return $act->getResultAssembler()->exceptionToHttpResponse($e);
		}
	}
}
