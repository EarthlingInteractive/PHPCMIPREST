<?php

class EarthIT_CMIPREST_Util
{
	public static function first(array $things, $default=null) {
		foreach($things as $thing) return $thing;
		return $default;
	}
	
	public static function parseBoolean( $v ) {
		if( is_bool($v) ) return $v;
		if( is_string($v) ) switch(strtolower($v)) {
			case '1': case 'true': case 'yes': case 'on': return true;
			case '0': case 'false': case 'no': case 'off': return false;		
		}
		if( is_numeric($v) ) {
			if( $v == 0 ) return false;
			if( $v == 1 ) return true;
		}
		throw new Exception("Invalid booleanesque value: ".var_export($v,true)." (try using 'true', 'false', '0', or '1')");
	}
	
	/**
	 * Convert a value to the named PHP scalar type using PHP's default conversion.
	 *
	 * @deprecated use Storage_Util::cast
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

	/**
	 * @deprecated use Storage_Util::itemId
	 */
	public static function itemId( EarthIT_Schema_ResourceClass $rc, array $item ) {
		return EarthIT_Storage_Util::itemId($item, $rc);
	}
	
	/**
	 * return array of field name => field value for the primary key fields encoded in $id
	 *
	 * @deprecated use Storage_Util::itemIdToFieldValues
	 */
	public static function idToFieldValues( EarthIT_Schema_ResourceClass $rc, $id) {
		return EarthIT_Storage_Util::itemIdToFieldValues($id, $rc);
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
	protected static function fieldPropertyValue( $f, $propUri, $nonFakeDefault=null, $fakeDefault=null ) {
		$v = $f->getFirstPropertyValue($propUri);
		if( $v !== null ) return $v;
		
		$isFake = $f->getFirstPropertyValue(EarthIT_CMIPREST_NS::IS_FAKE_FIELD);
		return $isFake ? $fakeDefault : $nonFakeDefault;
	}
	
	protected static function fieldsWithProperty( array $l, $propUri, $nonFakeDefault=null, $fakeDefault=null ) {
		$filtered = array();
		foreach( $l as $k=>$f ) {
			if( self::fieldPropertyValue($f, $propUri, $nonFakeDefault, $fakeDefault) ) {
				$filtered[$k] = $f;
			}
		}
		return $filtered;
	}
	
	/** @api */
	public static function restReturnableFields( EarthIT_Schema_ResourceClass $rc ) {
		return self::fieldsWithProperty($rc->getFields(), EarthIT_CMIPREST_NS::IS_RETURNED_BY_REST_SERVICES, true, false);
	}
	
	/** @api */
	public static function storableFields( EarthIT_Schema_ResourceClass $rc ) {
		return self::fieldsWithProperty($rc->getFields(), EarthIT_CMIPREST_NS::HAS_A_DATABASE_COLUMN, true, false);
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
	
	public static function getItemById( EarthIT_CMIPREST_Storage $storage, EarthIT_Schema_ResourceClass $rc, $itemId ) {
		return EarthIT_Storage_Util::getItemById( $itemId, $rc, $storage );
	}

	public static function postItem( EarthIT_Storage_ItemSaver $storage, EarthIT_Schema_ResourceClass $rc, array $itemData ) {
		$itemData = EarthIT_Storage_Util::castItemFieldValues($itemData,$rc);
		return EarthIT_CMIPREST_Util::first( $storage->saveItems( array($itemData), $rc, array(
			EarthIT_Storage_ItemSaver::RETURN_SAVED => true,
			EarthIT_Storage_ItemSaver::ON_DUPLICATE_KEY => EarthIT_Storage_ItemSaver::ODK_UPDATE) ) );
	}
	
	public static function putItem( EarthIT_Storage_ItemSaver $storage, EarthIT_Schema_ResourceClass $rc, $itemId, array $itemData ) {
		$itemData = EarthIT_CMIPREST_Util::mergeEnsuringNoContradictions(
			EarthIT_Storage_Util::itemIdToFieldValues($itemId,$rc),
			EarthIT_Storage_Util::castItemFieldValues($itemData,$rc));
		return EarthIT_CMIPREST_Util::first( $storage->saveItems( array($itemData), $rc, array(
			EarthIT_Storage_ItemSaver::RETURN_SAVED => true,
			EarthIT_Storage_ItemSaver::ON_DUPLICATE_KEY => EarthIT_Storage_ItemSaver::ODK_REPLACE) ) );
	}
	
	public static function patchItem( EarthIT_Storage_ItemSaver $storage, EarthIT_Schema_ResourceClass $rc, $itemId, array $itemData ) {
		$itemData = EarthIT_CMIPREST_Util::mergeEnsuringNoContradictions(
			EarthIT_Storage_Util::itemIdToFieldValues($itemId,$rc),
			EarthIT_Storage_Util::castItemFieldValues($itemData,$rc));
		return EarthIT_CMIPREST_Util::first( $storage->saveItems( array($itemData), $rc, array(
			EarthIT_Storage_ItemSaver::RETURN_SAVED => true,
			EarthIT_Storage_ItemSaver::ON_DUPLICATE_KEY => EarthIT_Storage_ItemSaver::ODK_UPDATE) ) );
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
	
	public static function jsonResponse( $status, $data, $headers=array() ) {
		$headers['content-type'] = 'application/json';
		return Nife_Util::httpResponse( $status, new EarthIT_JSON_PrettyPrintedJSONBlob($data), $headers );
	}
	
	public static function multiErrorResponse( $status, array $errors, array $headers=array() ) {
		return self::jsonResponse($status, array('errors'=>$errors), $headers);
	}
	
	/**
	 * Create a response to indicate a single error
	 * from a status code, message, and list of notes.
	 */
	public static function singleErrorResponse( $status, $message, array $notes=array(), array $headers=array() ) {
		return self::multiErrorResponse($status, array(self::errorStructure( $message, $notes )), $headers);
	}
	
	const BASIC_WWW_AUTHENTICATION_REALM = 'basicWwwAuthenticationRealm';
	
	/**
	 * @param Exception $e the error
	 * @param boolean $userIsAuthenticated whether this was triggered by a logged-in user;
	 *   used to determine whether a 401 or 403 is appropriate in response to authorization failures.
	 * @param array $options optional array of
	 *   Util::BASIC_WWW_AUTHENTICATION_REALM => realm to indicate in 'WWW-Authenticate: Basic' headers, if any
	 *     (otherwise, those headers will not be sent)
	 */
	public static function exceptionalNormalJsonHttpResponse( Exception $e, $userIsAuthenticated=false, array $options=array() ) {
		if( $e instanceof EarthIT_CMIPREST_ActionUnauthorized ) {
			$act = $e->getAction();
			$status = $userIsAuthenticated ? 403 : 401;
			$headers = array();
			if( !$userIsAuthenticated and !empty($options[self::BASIC_WWW_AUTHENTICATION_REALM]) ) {
				$headers['www-authenticate'] = "Basic realm=\"{$options[self::BASIC_WWW_AUTHENTICATION_REALM]}\"";
			}
			return EarthIT_CMIPREST_Util::singleErrorResponse( $status, $e->getSimpleMessage(), $e->getNotes(), $headers );
		} else if( $e instanceof EarthIT_CMIPREST_ResourceNotExposedViaService ) {
			return EarthIT_CMIPREST_Util::singleErrorResponse( 404, $e->getMessage() );
		} else if( $e instanceof EarthIT_CMIPREST_ActionInvalid ) {
			return EarthIT_CMIPREST_Util::multiErrorResponse( 409, $e->getErrorDetails() );
		} else if( $e instanceof EarthIT_Schema_NoSuchResourceClass ) {
			return EarthIT_CMIPREST_Util::singleErrorResponse( 404, $e->getMessage() );
		} else {
			throw $e;
		}
	}
	
	////
	
	public static function encodeItem( array $item, EarthIT_Schema_ResourceClass $rc, EarthIT_CMIPREST_ItemCodec $codec ) {
		return self::first($codec->encodeItems(array($item), $rc));
	}
	public static function decodeItem( array $item, EarthIT_Schema_ResourceClass $rc, EarthIT_CMIPREST_ItemCodec $codec ) {
		return self::first($codec->decodeItems(array($item), $rc));
	}
}
