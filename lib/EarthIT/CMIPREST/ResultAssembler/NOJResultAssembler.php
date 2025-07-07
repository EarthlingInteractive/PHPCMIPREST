<?php

/**
 * NOJ = 'nested obvious JSON'
 */
class EarthIT_CMIPREST_ResultAssembler_NOJResultAssembler implements EarthIT_CMIPREST_ResultAssembler
{
	const SUCCESS = "You're Winner!";
	const DELETED = "BALEETED!";
	
	const KEY_BY_IDS = 'keyItemsById';
	const INCLUDE_JSON_METADATA = 'includeJsonMetadata';
	
	protected $method;
	protected $keyByIds;
	protected $includeJsonMetadata;
	protected $basicWwwAuthenticationRealm;
	
	public function __construct($method, $options=array()) {
		if( is_bool($options) ) $options = array(self::KEY_BY_IDS => $options); // For backward combatibility!
		$this->method = $method;
		$this->keyByIds = isset($options[self::KEY_BY_IDS]) ? $options[self::KEY_BY_IDS] : true;
		$this->includeJsonMetadata = isset($options[self::INCLUDE_JSON_METADATA]) ? $options[self::INCLUDE_JSON_METADATA] : false;
		$this->basicWwwAuthenticationRealm =
			isset($options[EarthIT_CMIPREST_Util::BASIC_WWW_AUTHENTICATION_REALM]) ?
			$options[EarthIT_CMIPREST_Util::BASIC_WWW_AUTHENTICATION_REALM] : null;
	}
	
	protected function jsonTyped($v, $jt) {
		if( !$this->includeJsonMetadata ) return $v;
		if( !is_array($v) ) return $v;
		$v[EarthIT_JSON::JSON_TYPE] = $jt;
		return $v;
	}
	
	// TODO: Make configurable
	protected function fieldRestName( EarthIT_Schema_ResourceClass $rc, EarthIT_Schema_Field $f ) {
		// When emitting JSON, format names as the JS does
		return EarthIT_Schema_WordUtil::toCamelCase($f->getName());
	}
	
	protected function internalValueToRest( EarthIT_Schema_DataType $dt, $v ) {
		return $v;
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
	
	protected function shouldKeyRestItemsById( EarthIT_Schema_ResourceClass $rc ) {
		if( !$this->keyByIds ) return false;
		$pk = $rc->getPrimaryKey();
		return $pk !== null && count($pk->getFieldNames()) > 0;
	}
	
	/**
	 * Convert the given rows from DB to REST format according to the
	 * specified resource class.
	 */
	protected function _q45( EarthIT_Schema_ResourceClass $rc, array $items ) {
		$restObjects = array();
		$keyByIds = $this->shouldKeyRestItemsById($rc);
		foreach( $items as $item ) {
			$restItem = $this->internalObjectToRest($rc, $item);
			if( $keyByIds ) {
				$restObjects[EarthIT_Storage_Util::itemId($item,$rc)] = $restItem;
			} else {
				$restObjects[] = $restItem;
			}
		}
		return $this->jsonTyped($restObjects, $keyByIds ? EarthIT_JSON::JT_OBJECT : EarthIT_JSON::JT_LIST);
	}
	
	protected function assembleMultiItemResult( EarthIT_Schema_ResourceClass $rootRc, array $johnCollections, array $relevantObjects ) {
		$relevantRestObjects = array();
		foreach( $johnCollections as $path => $johns ) {
			// Figure out what resource class of items we got, here
			$targetRc = count($johns) == 0 ? $rootRc : $johns[count($johns)-1]->targetResourceClass;
			$relevantRestObjects[$path] = $this->_q45( $targetRc, $relevantObjects[$path] );
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
					if( $ok === EarthIT_JSON::JSON_TYPE ) continue;
					$relations = array();
					// This is probably slightly broken when keyByIds=true
					// but the class has no primary key.
					// TODO: call shouldKeyItemsById on the class instead of just looking at $this->keyByIds.
					foreach( $relevantRestObjects[$path] as $tk=>$tv ) {
						if( $tk === EarthIT_JSON::JSON_TYPE ) continue;
						$matches = true;
						foreach( $matchFields as $trf=>$orf ) {
							if( $tv[$trf] != $ov[$orf] ) $matches = false;
						}
						if( $matches ) {
							if( $this->keyByIds ) {
								$relations[$tk] =& $relevantRestObjects[$path][$tk];
							} else {
								$relations[] =& $relevantRestObjects[$path][$tk];
							}
						}
					}
					$relations = $this->jsonTyped($relations, $this->keyByIds ? EarthIT_JSON::JT_OBJECT : EarthIT_JSON::JT_LIST);
					if( $plural ) {
						$relevantRestObjects[$originPath][$ok][$lastPathPart] = $relations;
					} else {
						$relevantRestObjects[$originPath][$ok][$lastPathPart] = null;
						foreach( $relations as $k=>$_ ) {
							$relevantRestObjects[$originPath][$ok][$lastPathPart] =& $relations[$k];
							break;
						}
					}
				}
			}
		}
		
		return $relevantRestObjects['root'];
	}
	
	protected function assembleSingleItemResult( EarthIT_Schema_ResourceClass $rootRc, array $johnCollections, array $relevantObjects ) {
		foreach( $this->assembleMultiItemResult($rootRc, $johnCollections, $relevantObjects) as $item ) return $item;
		return null;
	}
	protected function assembleSuccessResult( EarthIT_Schema_ResourceClass $rootRc, array $johnCollections, array $relevantObjects ) {
		return self::SUCCESS;
	}
	protected function assembleDeletedResult( EarthIT_Schema_ResourceClass $rootRc, array $johnCollections, array $relevantObjects ) {
		return self::BALEETED;
	}
	
	/** @override */
	public function needsResult() {
		switch( $this->method ) {
		case 'assembleSuccessResult': return false;
		case 'assembleDeletedResult': return false;
		default: return true;
		}
	}

	/** @override */
	public function assembleResult( EarthIT_CMIPREST_ActionResult $result, EarthIT_CMIPREST_Action|null $action=null, $ctx=null ) {
		if( !($result instanceof EarthIT_CMIPREST_StorageResult) )
			throw new Exception(get_class($this)." doesn't know how to assemble things that aren't StorageResults");
		$meth = $this->method;
		return $this->$meth( $result->getRootResourceClass(), $result->getJohnCollections(), $result->getItemCollections());
	}
	
	/** @override */
	public function assembledResultToHttpResponse( $rez, EarthIT_CMIPREST_Action|null $action=null, $ctx=null ) {
		if( $rez === self::SUCCESS or $rez === self::DELETED ) {
			return Nife_Util::httpResponse("204 Okay");
		} else if( $rez === null ) {
			return Nife_Util::httpResponse(404, new EarthIT_JSON_PrettyPrintedJSONBlob(null), array('content-type'=>'application/json'));
		} else {
			return Nife_Util::httpResponse(200, new EarthIT_JSON_PrettyPrintedJSONBlob($rez), array('content-type'=>'application/json'));
		}
	}

	/** @override */
	public function exceptionToHttpResponse( Exception $e, EarthIT_CMIPREST_Action|null $action=null, $ctx=null ) {
		$userIsAuthenticated = ($ctx and method_exists($ctx,'userIsAuthenticated')) ? $ctx->userIsAuthenticated() : false;
		return EarthIT_CMIPREST_Util::exceptionalNormalJsonHttpResponse($e, $userIsAuthenticated, array(
			EarthIT_CMIPREST_Util::BASIC_WWW_AUTHENTICATION_REALM => $this->basicWwwAuthenticationRealm
		));
	}
	
	public function __get($k) {
		switch($k) {
		case 'keyByIds': case 'basicWwwAuthenticationRealm':
			return $this->$k;
		}
	}
}
