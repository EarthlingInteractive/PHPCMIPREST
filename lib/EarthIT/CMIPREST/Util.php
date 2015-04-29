<?php

class EarthIT_CMIPREST_Util
{
	/**
	 * Convert a value to the named PHP scalar type using PHP's default conversion.
	 */
	public static function cast( $value, $phpType ) {
		if( $phpType === null or $value === null ) return $value;
		
		switch( $phpType ) {
		case 'string': return (string)$value;
		case 'float': return (float)$value;
		case 'int': return (int)$value;
		case 'bool':
			if( is_bool($value) ) return $value;
			if( is_numeric($value) ) {
				if( $value == 1 ) return true;
				if( $value == 0 ) return false;
			}
			if( is_string($value) ) {
				if( in_array($value, array('yes','true','on')) ) return true;
				if( in_array($value, array('no','false','off','')) ) return false;
			}
			throw new Exception("Invalid boolean representation: ".var_export($value,true));
		default:
			throw new Exception("Don't know how to cast to PHP type '$phpType'.");
		}
	}
	
	public static function getIdRegex( EarthIT_Schema_ResourceClass $rc ) {
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
	
	public static function itemId( EarthIT_Schema_ResourceClass $rc, array $item ) {
		$pk = $rc->getPrimaryKey();
		if( $pk === null or count($pk->getFieldNames()) == 0 ) return null;
		
		$fields = $rc->getFields();
		$parts = array();
		foreach( $pk->getFieldNames() as $fn ) {
			$parts[] = $item[$fn];
		}
		return implode("-", $parts);
	}
	
	/**
	 * return array of field name => field value for the primary key fields encoded in $id
	 */
	public static function idToFieldValues( EarthIT_Schema_ResourceClass $rc, $id) {
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
			$idFieldValues[$fn] = EarthIT_CMIPREST_Util::cast($bif[$i], $field->getType()->getPhpTypeName());
			++$i;
		}
		
		return $idFieldValues;
	}
		
	public static function mergeEnsuringNoContradictions() {
		$arrays = func_get_args();
		$result = array();
		foreach( $arrays as $arr ) {
			foreach( $arr as $k=>$v ) {
				if( isset($result[$k]) ) {
					if( $result[$k] !== $v ) {
						throw new Exception("Conflicting values given for '$k': ".json_encode($result[$k]).", ".json_encode($v));
					}
				} else {
					$result[$k] = $v;
				}
			}
		}
		return $result;
	}
	
	/**
	 * Get a field property value, taking into account
	 * whether the field is fake or not, and defaults for either case.
	 */
	protected static function fieldPropertyValue( $f, $propUri, $nonFakeDefault=true, $fakeDefault=false ) {
		$v = $f->getFirstPropertyValue($propUri);
		if( $v !== null ) return $v;
		
		$isFake = $f->getFirstPropertyValue(EarthIT_CMIPREST_NS::IS_FAKE_FIELD);
		return $isFake ? $fakeDefault : $nonFakeDefault;
	}
	
	protected static function fieldsWithProperty( array $l, $propUri, $nonFakeDefault=true, $fakeDefault=false ) {
		$filtered = array();
		foreach( $l as $k=>$f ) {
			if( self::fieldPropertyValue($f, $propUri) ) {
				$filtered[$k] = $f;
			}
		}
		return $filtered;
	}
	
	public static function restReturnableFields( EarthIT_Schema_ResourceClass $rc ) {
		return self::fieldsWithProperty($rc->getFields(), EarthIT_CMIPREST_NS::IS_RETURNED_BY_REST_SERVICES);
	}
	
	public static function storableFields( EarthIT_Schema_ResourceClass $rc ) {
		return self::fieldsWithProperty($rc->getFields(), EarthIT_CMIPREST_NS::HAS_A_DATABASE_COLUMN);
	}

	/**
	 * Finds a resource class in the given schema based on the given
	 * collection name, using a fuzzy name comparison so that e.g.
	 * either 'some things', 'someThings', or 'some-things' should
	 * match the 'some thing' type, or a type with "some things" as its
	 * collection name.
	 */
	public static function getResourceClassByCollectionName( $schema, $collectionName ) {
		$minCollectionName = EarthIT_Schema_WordUtil::minimize($collectionName);
		foreach( $schema->getResourceClasses() as $rc ) {
			if( $minCollectionName == EarthIT_Schema_WordUtil::minimize(
				$rc->getFirstPropertyValue(EarthIT_CMIPREST_NS::COLLECTION_NAME)
			) ) {
				return $rc;
			}
		}
		return $schema->getResourceClass( EarthIT_Schema_WordUtil::depluralize($collectionName) );
	}
	
	public static function itemIdToSearchParameters( EarthIT_Schema_ResourceClass $rc, $id ) {
		$fieldValues = EarthIT_CMIPREST_Util::idToFieldValues( $rc, $id );
		$fieldMatchers = array();
		foreach( $fieldValues as $fieldName => $value ) {
			$fieldMatchers[$fieldName] = new EarthIT_CMIPREST_FieldMatcher_Equal($value);
		}
		return new EarthIT_CMIPREST_SearchParameters($fieldMatchers, array(), 0, null);
	}
	
	public static function getItemById( EarthIT_CMIPREST_Storage $storage, EarthIT_Schema_ResourceClass $rc, $itemId ) {
		$sp = self::itemIdToSearchParameters($rc, $itemId);
		$results = $storage->search( $rc, $sp, array() )['root'];
		if( count($results) == 0 ) return null;
		if( count($results) == 1 ) return $results[0];
		throw new Exception("Multiple ".$rc->getName()." records found with ID = '".$itemId."'");
	}
	
	//// Nife_HTTP_Response generation
	
	/**
	 * Create a single error structures
	 * from a message and list of notes.
	 */
	protected static function errorStructure( $message, array $notes=array() ) {
		return array(
			'message' => $message,
			'notes' => $notes
		);
	}
	
	public static function jsonResponse( $status, $data ) {
		return Nife_Util::httpResponse( $status, new EarthIT_JSON_PrettyPrintedJSONBlob($data), 'application/json' );
	}
	
	public static function multiErrorResponse( $status, array $errors ) {
		return self::jsonResponse($status, array('errors'=>$errors));
	}
	
	/**
	 * Create a response to indicate a single error
	 * from a status code, message, and list of notes.
	 */
	public static function singleErrorResponse( $status, $message, array $notes=array() ) {
		return self::multiErrorResponse($status, array(self::errorStructure( $message, $notes )));
	}
	
	public static function first(array $things, $default=null) {
		foreach($things as $thing) return $thing;
		return $default;
	}
	
	public static function encodeItem( array $item, EarthIT_Schema_ResourceClass $rc, EarthIT_CMIPREST_ItemCodec $codec ) {
		return self::first($codec->encodeItems(array($item), $rc));
	}
	public static function decodeItem( array $item, EarthIT_Schema_ResourceClass $rc, EarthIT_CMIPREST_ItemCodec $codec ) {
		return self::first($codec->decodeItems(array($item), $rc));
	}
}
