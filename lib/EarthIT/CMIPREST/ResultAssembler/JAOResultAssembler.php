<?php

// TODO: Rename to JAOResultAssembler
/** The JSONAPI.org format */
class EarthIT_CMIPREST_ResultAssembler_JAOResultAssembler implements EarthIT_CMIPREST_ResultAssembler
{
	const SUCCESS = "You're Winner!";
	const DELETED = "BALEETED!";
	
	protected $schema;
	protected $nameFormatter;
	protected $plural;
	
	/**
	 * @param EarthIT_Schema $schema the schema that we're emitting responses for
	 * @param callable $nameFormatter a string -> string function
	 *   to provide 'REST names' for everything (probably camelCase).
	 * @param bolean $plural true if we're returning a set of objects,
	 *   false if we're just returning an object
	 */
	public function __construct(EarthIT_Schema $schema, $nameFormatter, $plural) {
		$this->schema = $schema;
		$this->nameFormatter = $nameFormatter;
		$this->plural = $plural;
	}
	
	// TODO: Make configurable
	protected function fieldJaoName( EarthIT_Schema_ResourceClass $rc, EarthIT_Schema_Field $f ) {
		// When emitting JSON, format names as the JS does
		return EarthIT_Schema_WordUtil::toCamelCase($f->getName());
	}
	
	protected function internalValueToJao( EarthIT_Schema_DataType $dt, $v ) {
		return $v;
	}
	
	protected static function nonLinkNonPkFields( EarthIT_Schema_ResourceClass $rc ) {
		$pk = $rc->getPrimaryKey();
		$pkFieldNames = array();
		$fkFieldNames = array();
		$normalFields = array();
		if( $pk !== null ) foreach( $pk->getFieldNames() as $fn ) $pkFieldNames[$fn] = $fn;
		foreach( $rc->getReferences() as $fk ) foreach( $fk->getOriginFieldNames() as $fn ) $fkFieldNames[$fn] = $fn;
		foreach( EarthIT_CMIPREST_Util::restReturnableFields($rc) as $k=>$f ) {
			if( !isset($pkFieldNames[$k]) and !isset($fkFieldNames[$k]) ) $normalFields[$k] = $f;
		}
		return $normalFields;
	}
	
	protected function jaoTypeName( EarthIT_Schema_ResourceClass $rc ) {
		return call_user_func( $this->nameFormatter,
			$rc->getFirstPropertyValue(EarthIT_CMIPREST_NS::COLLECTION_NAME) ?:
			EarthIT_Schema_WordUtil::pluralize($rc->getName())
		);
	}
	protected function jaoFieldName( EarthIT_Schema_Field $f, EarthIT_Schema_ResourceClass $rc ) {
		return call_user_func( $this->nameFormatter, $f->getName() );
	}
	
	protected function internalObjectToJao( EarthIT_Schema_ResourceClass $rc, array $fieldValues ) {
		$result = array();
		
		// Assemble 'id' field, if there is one:
		$pkValues = array();
		foreach( $rc->getPrimaryKey()->getFieldNames() as $pkFn ) {
			$pkValues[] = $fieldValues[$pkFn];
		}
		if( count($pkValues) > 0 ) {
			$result['id'] = implode('-', $pkValues);
		}
		
		// Assemble normal, non-key, non-link fields:
		foreach( self::nonLinkNonPkFields($rc) as $k=>$field ) {
			if( array_key_exists($k, $fieldValues) ) {
				$result[$this->fieldJaoName($rc, $field)] =
					$this->internalValueToJao($field->getType(), $fieldValues[$field->getName()]);
			}
		}
		
		// Add the JAO 'type' field
		$result['type'] = $this->jaoTypeName($rc);
		
		// Add links
		foreach( $rc->getReferences() as $ref ) {
			$targetRc = $this->schema->getResourceClass($ref->getTargetClassName());
			$match = array();
			$originFieldNames = $ref->getOriginFieldNames();
			$targetFieldNames = $ref->getTargetFieldNames();
			for( $i=0; $i<count($originFieldNames); ++$i ) {
				if( !isset($fieldValues[$originFieldNames[$i]]) ) continue 2;
				
				$match[self::jaoFieldName($targetRc->getField($targetFieldNames[$i]), $targetRc)] = $fieldValues[$originFieldNames[$i]];
			}
			$result['links'][EarthIT_Schema_WordUtil::toCamelCase($ref->getName())]['linkage'] = array(
				'type' => self::jaoTypeName($targetRc)
			) + $match;
		}
		return $result;
	}
	
	/**
	 * Convert the given rows from internal to REST format according to the
	 * specified resource class.
	 */
	protected function _q45( EarthIT_Schema_ResourceClass $rc, array $items ) {
		$restObjects = array();
		foreach( $items as $item ) {
			$restObjects[] = $this->internalObjectToJao($rc, $item);
		}
		return $restObjects;
	}
	
	/** @override */
	public function needsResult() {
		return true;
	}
	
	/** @override */
	public function assembleResult( EarthIT_CMIPREST_StorageResult $result, TOGoS_Action $action=null, $ctx=null ) {
		$rootRc = $result->getRootResourceClass();
		$johnCollections = $result->getJohnCollections();
		$relevantObjects = $result->getItemCollections();
		
		$relevantRestObjects = array();
		foreach( $johnCollections as $path => $johns ) {
			// Figure out what resource class of items we got, here
			$targetRc = count($johns) == 0 ? $rootRc : $johns[count($johns)-1]->targetResourceClass;
			$relevantRestObjects[$path] = $this->_q45( $targetRc, $relevantObjects[$path] );
		}
		
		if( $this->plural ) {
			$rezData = $relevantRestObjects['root'];
		} else {
			// Justin says:
			// "JSON API doesn't support null values for the top-level data object.
			// "It needs to be an empty object."
			// - 2015-12-21
			$rezData = array();
			foreach( $relevantRestObjects['root'] as $rezData ) break;
		}
		
		$rez = array( 'data' => $rezData );
		foreach( $relevantRestObjects as $path => $objects ) {
			if( $path != 'root' ) foreach( $objects as $obj ) $rez['included'][] = $obj;
		}
		return $rez;
	}
	
	/** @override */
	public function assembledResultToHttpResponse( $rez ) {
		if( $rez === self::SUCCESS or $rez === self::DELETED ) {
			return Nife_Util::httpResponse("204 Okay");
		} else if( $rez === null ) {
			return Nife_Util::httpResponse(404, new EarthIT_JSON_PrettyPrintedJSONBlob(null), array('content-type'=>'application/json'));
		} else {
			return Nife_Util::httpResponse(200, new EarthIT_JSON_PrettyPrintedJSONBlob($rez), array('content-type'=>'application/vnd.api+json'));
		}
	}

	/** @override */
	public static function exceptionToHttpResponse( Exception $e ) {
		return EarthIT_CMIPREST_Util::exceptionalNormalJsonHttpResponse($e);
	}
}
