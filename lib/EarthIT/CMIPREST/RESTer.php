<?php

/*
 * TODO:
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
	protected $authorizer;
	protected $preUpdateListeners;
	
	public function __construct( $params ) {
		if( !is_array($params) ) {
			throw new Exception("Parameters to RESTer constructor must be an array");
		}
		
		$params += array(
			'storage' => null,
			'dbAdapter' => null,
			'dbNamer' => null,
			'schema' => null,
			'authorizer' => new EarthIT_CMIPREST_RESTActionAuthorizer_DefaultRESTActionAuthorizer(),
			'preUpdateListeners' => array(),
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
		
		$this->authorizer = $params['authorizer'];
		$this->preUpdateListeners = $params['preUpdateListeners'];
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
			$rc = $act->getResourceClass();
			if( !$rc->hasRestService() ) {
				throw new EarthIT_CMIPREST_ResourceNotExposedViaService($act, array('message'=>"'".$rc->getName()."' records are not exposed via services"));
			}
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
		if( !$preAuth ) throw new Exception("\$preAuth = false passed to doSearchAction.");
		
		$rc = $act->getResourceClass();
		$search = $act->getSearch();
		$queryParams = array();
		$relevantObjects = $this->storage->johnlySearchItems( $search, $act->getJohnBranches() );
		$johnCollections = $this->collectJohns( $act->getJohnBranches(), 'root' );

		$searchOptions = $act->getSearchOptions();
		$vizMode = isset($searchOptions[EarthIT_CMIPREST_RESTActionAuthorizer::SEARCH_RESULT_VISIBILITY_MODE]) ? 
			$searchOptions[EarthIT_CMIPREST_RESTActionAuthorizer::SEARCH_RESULT_VISIBILITY_MODE] : EarthIT_CMIPREST_RESTActionAuthorizer::SRVM_BINARY;
		
		// If we need to post-authorize, do it.
		if(
			$preAuth === EarthIT_CMIPREST_RESTActionAuthorizer::AUTHORIZED_IF_RESULTS_VISIBLE or
			$vizMode !== EarthIT_CMIPREST_RESTActionAuthorizer::SRVM_BINARY
		) {
			foreach( $johnCollections as $path => $johns ) {
				// Figure out what resource class of items we got, here
				$targetRc = count($johns) == 0 ? $rc : $johns[count($johns)-1]->targetResourceClass;
				
				switch( $vizMode ) {
				case EarthIT_CMIPREST_RESTActionAuthorizer::SRVM_BINARY:
					// Ensure that they're visisble
					if( !$this->authorizer->itemsVisible($relevantObjects[$path], $targetRc, $ctx, $authorizationExplanation) ) {
						throw new EarthIT_CMIPREST_ActionUnauthorized($act, $ctx, $authorizationExplanation);
					}
					break;
				case EarthIT_CMIPREST_RESTActionAuthorizer::SRVM_RECURSIVE_ALLOWED_ONLY:
					if( !($this->authorizer instanceof EarthIT_CMIPREST_RESTActionAuthorizer2) ) {
						throw new Exception(
							"Can't filter search results by visibility because authorizer (".
							get_class($this->authorizer)." doesn't implement RESTActionAuthorizer2");
					}
					$relevantObjects[$path] =
						$this->authorizer->visibleItems( $relevantObjects[$path], $targetRc, $ctx, $authorizationExplanation);
					break;
				default:
					throw new Exception("Unsupported search result visibility mode: {$vizMode}");
				}
			}
		}
		
		return $act->getResultAssembler()->assembleResult(
			new EarthIT_CMIPREST_StorageResult($rc, $johnCollections, $relevantObjects),
			$act, $ctx);
	}
	
	/**
	 * Assemble results with no johns.  i.e. a simple collection of one type of objects
	 */
	protected function assembleSimpleResult($act, $rc, array $items, $ctx) {
		$assembler = $act->getResultAssembler();
		if( $assembler === null ) {
			throw new Exception("No result assembler specified by ".get_class($act));
		}
		
		return $assembler->assembleResult(
			new EarthIT_CMIPREST_StorageResult($rc, array('root'=>array()), array('root'=>$items)),
			$act, $ctx );
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
			throw new Exception(
				"preAuthorizeSimpleAction should only return true or false ".
				"for non-search actions, but it returned ".var_export($preAuth,true));
		}
		
		// Otherwise it's A-Okay!
		
		foreach( $this->preUpdateListeners as $pul ) {
			call_user_func($pul, $act, $ctx);
		}
		
		if( $act instanceof EarthIT_CMIPREST_RESTAction_PostItemAction ) {
			$rc = $act->getResourceClass();
			return $this->assembleSimpleResult($act, $rc, array($this->storage->postItem($rc, $act->getItemData())), $ctx);
		} else if( $act instanceof EarthIT_CMIPREST_RESTAction_PutItemAction ) {
			$rc = $act->getResourceClass();
			return $this->assembleSimpleResult($act, $rc, array($this->storage->putItem($rc, $act->getItemId(), $act->getItemData())), $ctx);
		} else if( $act instanceof EarthIT_CMIPREST_RESTAction_PatchItemAction ) {
			$rc = $act->getResourceClass();
			return $this->assembleSimpleResult($act, $rc, array($this->storage->patchItem($rc, $act->getItemId(), $act->getItemData())), $ctx);
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
		return $act->getResultAssembler()->assembleResult(new EarthIT_CMIPREST_CompoundActionResult($subActionResults));
	}
	
	/**
	 * @api
	 * @return Nife_HTTP_Response|RESTer::SUCCESS|array
	 */
	public function doAction( EarthIT_CMIPREST_RESTAction $act, $ctx ) {
		// TODO: Validate and preAuthorize before doing anything
		// instead of validating/authorizing each action as it is run.
		if( $act instanceof EarthIT_CMIPREST_RESTAction_SudoAction ) {
			foreach( $act->getSuIds() as $suId ) {
				$explanation = array();
				if( !$this->authorizer->sudoAllowed($suId, $ctx, $explanation) ) {
					throw new EarthIT_CMIPREST_ActionUnauthorized($act, $ctx, $explanation);
				}
			}
			$suCtx = EarthIT_CMIPREST_Util::suContext($ctx, $act->getSuIds(), $act->getPermissionMergeMode());
			return $this->doAction( $act->getAction(), $suCtx );
		} else if( $act instanceof EarthIT_CMIPREST_RESTAction_CompoundAction ) {
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
			return $act->getResultAssembler()->assembledResultToHttpResponse($rez, $act, $ctx);
		} catch( Exception $e ) {
			return $act->getResultAssembler()->exceptionToHttpResponse($e, $act, $ctx);
		}
	}
}
