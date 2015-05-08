<?php

class EarthIT_CMIPREST_ResultAssembler_NOJResultAssembler implements EarthIT_CMIPREST_ResultAssembler
{
	protected $method;
	protected $keyByIds;
	
	public function __construct($method, $keyByIds=true) {
		$this->method = $method;
		$this->keyByIds = $keyByIds;
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
	
	/**
	 * Convert the given rows from DB to REST format according to the
	 * specified resource class.
	 */
	protected function _q45( EarthIT_Schema_ResourceClass $rc, array $items ) {
		$restObjects = array();
		foreach( $items as $item ) {
			$restItem = $this->internalObjectToRest($rc, $item);
			if( $this->keyByIds and ($itemId = EarthIT_CMIPREST_Util::itemId($rc, $item)) !== null ) {
				$restObjects[$itemId] = $restItem;
			} else {
				$restObjects[] = $restItem;
			}
		}
		return $restObjects;
	}
	
	protected function assembleSearchResult( EarthIT_Schema_ResourceClass $rootRc, array $johnCollections, array $relevantObjects ) {
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
					$relations = array();
					foreach( $relevantRestObjects[$path] as $tk=>$tv ) {
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
	
	protected function assembleSingleResult( EarthIT_Schema_ResourceClass $rootRc, array $johnCollections, array $relevantObjects ) {
		foreach( $this->assembleSearchResult($rootRc, $johnCollections, $relevantObjects) as $item ) return $item;
		return null;
	}
	protected function assemblePostResult( EarthIT_Schema_ResourceClass $rootRc, array $johnCollections, array $relevantObjects ) {
		return $this->assembleSearchResult($rootRc, $johnCollections, $relevantObjects);
	}
	protected function assemblePutResult( EarthIT_Schema_ResourceClass $rootRc, array $johnCollections, array $relevantObjects ) {
		return $this->assembleSingleResult($rootRc, $johnCollections, $relevantObjects);
	}

	/** @override */
	public function needsResult() {
		return true;
	}

	/** @override */
	public function __invoke( EarthIT_CMIPREST_StorageResult $result ) {
		$meth = $this->method;
		return $this->$meth( $result->getRootResourceClass(), $result->getJohnCollections(), $result->getItemCollections() );
	}
}
