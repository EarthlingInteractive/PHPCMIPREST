<?php

/** The JSONAPI.org format */
class EarthIT_CMIPREST_ResultAssembler_JAO implements EarthIT_CMIPREST_ResultAssembler
{
	protected $schema;
	protected $nameFormatter;
	
	public function __construct(EarthIT_Schema $schema, callable $nameFormatter) {
		$this->schema = $schema;
		$this->nameFormatter = $nameFormatter;
	}
	
	// TODO: Make configurable
	protected function fieldRestName( EarthIT_Schema_ResourceClass $rc, EarthIT_Schema_Field $f ) {
		// When emitting JSON, format names as the JS does
		return EarthIT_Schema_WordUtil::toCamelCase($f->getName());
	}
	
	protected function internalValueToRest( EarthIT_Schema_DataType $dt, $v ) {
		return $v;
	}
	
	protected static function nonLinkFields( EarthIT_Schema_ResourceClass $rc ) {
		$pk = $rc->getPrimaryKey();
		$pkFieldNames = array();
		$fkFieldNames = array();
		$normalFields = array();
		if( $pk !== null ) foreach( $pk->getFieldNames() as $fn ) $pkFieldNames[$fn] = $fn;
		foreach( $rc->getReferences() as $fk ) foreach( $fk->getOriginFieldNames() as $fn ) $fkFieldNames[$fn] = $fn;
		foreach( $rc->getFields() as $k=>$f ) {
			if( isset($pkFieldNames[$k]) or !isset($fkFieldNames[$k]) ) $normalFields[$k] = $f;
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
	
	protected function internalObjectToRest( EarthIT_Schema_ResourceClass $rc, array $fieldValues ) {
		$result = array();
		foreach( self::nonLinkFields($rc) as $k=>$field ) {
			if( array_key_exists($k, $fieldValues) ) {
				$result[$this->fieldRestName($rc, $field)] =
					$this->internalValueToRest($field->getType(), $fieldValues[$field->getName()]);
			}
		}
		$result['type'] = $this->jaoTypeName($rc);
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
			$restObjects[] = $this->internalObjectToRest($rc, $item);
		}
		return $restObjects;
	}
	
	public function needsResult() {
		return true;
	}
	
	public function __invoke( EarthIT_CMIPREST_StorageResult $result ) {
		$rootRc = $result->getRootResourceClass();
		$johnCollections = $result->getJohnCollections();
		$relevantObjects = $result->getItemCollections();
		
		$relevantRestObjects = array();
		foreach( $johnCollections as $path => $johns ) {
			// Figure out what resource class of items we got, here
			$targetRc = count($johns) == 0 ? $rootRc : $johns[count($johns)-1]->targetResourceClass;
			$relevantRestObjects[$path] = $this->_q45( $targetRc, $relevantObjects[$path] );
		}
		
		$rez = array(
			'data' => $relevantRestObjects['root']
		);
		foreach( $relevantRestObjects as $path => $objects ) {
			if( $path != 'root' ) foreach( $objects as $obj ) $rez['included'][] = $obj;
		}
		return Nife_Util::httpResponse(200, new EarthIT_JSON_PrettyPrintedJSONBlob($rez), array('content-type'=>'application/vnd.api+json'));
	}
}
