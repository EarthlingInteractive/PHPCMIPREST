<?php

// TODO: Rename to JAOResultAssembler
/** The JSONAPI.org format */
class EarthIT_CMIPREST_ResultAssembler_JAOResultAssembler implements EarthIT_CMIPREST_ResultAssembler
{
	const SUCCESS = "You're Winner!";
	const DELETED = "BALEETED!";
	
	protected $schema;
	protected $schemaObjectNamer;
	protected $plural;
	
	/**
	 * @param EarthIT_Schema $schema the schema that we're emitting responses for
	 * @param EarthIT_Schema_SchemaObjectNamer $schemaObjectNamer namer
	 *   to provide 'REST names' for everything (probably camelCase).
	 * @param bolean $plural true if we're returning a set of objects,
	 *   false if we're just returning an object
	 */
	public function __construct(EarthIT_Schema $schema, EarthIT_Schema_SchemaObjectNamer $schemaObjectNamer, $plural) {
		$this->schema = $schema;
		$this->schemaObjectNamer = $schemaObjectNamer;
		$this->plural = $plural;
	}
	
	protected function jsonSerialize() {
		return array(
			'schemaObjectNamer' => $this->schemaObjectNamer,
			'plural' => $this->plural,
		);
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
		return $this->schemaObjectNamer->className($rc, true, $this->schema);
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
				$result[$this->schemaObjectNamer->fieldName($field, false, $rc, $this->schema)] =
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
				
				$match[$this->schemaObjectNamer->fieldName($targetRc->getField($targetFieldNames[$i]), false, $targetRc, $this->schema)] =
					$fieldValues[$originFieldNames[$i]];
			}
			$result['links'][EarthIT_Schema_WordUtil::toCamelCase($ref->getName())]['linkage'] = array(
				'type' => $this->jaoTypeName($targetRc)
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
	public function assembleResult( EarthIT_CMIPREST_ActionResult $result, EarthIT_CMIPREST_Action|null $action=null, $ctx=null ) {
		if( !($result instanceof EarthIT_CMIPREST_StorageResult) )
			throw new Exception(get_class($this)." doesn't know how to assemble things that aren't StorageResults");
		
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
	public function assembledResultToHttpResponse( $rez, EarthIT_CMIPREST_Action|null $action=null, $ctx=null ) {
		if( $rez === self::SUCCESS or $rez === self::DELETED ) {
			return Nife_Util::httpResponse("204 Okay");
		} else if( $rez === null ) {
			return Nife_Util::httpResponse(404, new EarthIT_JSON_PrettyPrintedJSONBlob(null), array('content-type'=>'application/json'));
		} else {
			return Nife_Util::httpResponse(200, new EarthIT_JSON_PrettyPrintedJSONBlob($rez), array('content-type'=>'application/vnd.api+json'));
		}
	}

	/** @override */
	public function exceptionToHttpResponse( Exception $e, EarthIT_CMIPREST_Action|null $action=null, $ctx=null ) {
		return EarthIT_CMIPREST_Util::exceptionalNormalJsonHttpResponse($e);
	}
}
