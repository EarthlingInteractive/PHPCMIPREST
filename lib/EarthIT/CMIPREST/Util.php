<?php

class EarthIT_CMIPREST_Util
{
	public static function describe( $thing ) {
		if( $thing === null   ) return "null";
		if( $thing === true   ) return "true";
		if( $thing === false  ) return "false";
		if( is_object($thing) ) return "a ".get_class($thing);
		return "a ".gettype($thing);
	}
	
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
		return EarthIT_Storage_Util::itemIdRegex( $rc );
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
	
	const ODK_KEEP = 'keep';
	const ODK_REPLACE = 'overwrite';
	const ODK_ERROR = 'error';
	
	public static function merge( array $arrays, $onDuplicateKey=self::ODK_ERROR, array &$collisions=array() ) {
		$result = array();
		foreach( $arrays as $arr ) {
			foreach( $arr as $k=>$v ) {
				if( isset($result[$k]) ) {
					if( $result[$k] !== $v ) {
						switch( $onDuplicateKey ) {
						case self::ODK_KEEP:
							$collisions[$k] = $v;
							break;
						case self::ODK_REPLACE:
							$collisions[$k] = $result[$k];
							$result[$k] = $v;
							break;
						default:
							throw new Exception("Conflicting values given for '$k': ".json_encode($result[$k]).", ".json_encode($v));
						}
					}
				} else {
					$result[$k] = $v;
				}
			}
		}
		return $result;
	}
	
	public static function mergeEnsuringNoContradictions() {
		$arrays = func_get_args();
		return call_user_func( array(__CLASS__, 'merge'), $arrays, self::ODK_ERROR );
	}
	
	/**
	 * Get a field property value, taking into account
	 * whether the field is fake or not, and defaults for either case.
	 * 
	 * @api
	 */
	public static function fieldPropertyValue( EarthIT_Schema_Field $f, $propUri, $nonFakeDefault=null, $fakeDefault=null ) {
		$v = $f->getFirstPropertyValue($propUri);
		if( $v !== null ) return $v;
		
		$isFake = $f->getFirstPropertyValue(EarthIT_CMIPREST_NS::IS_FAKE_FIELD);
		return $isFake ? $fakeDefault : $nonFakeDefault;
	}
	
	/**
	 * Return a subset of the passed in fields with the specified property,
	 * using $nonFakeDefault and $fakeDefault as the property value for
	 * non-fake and fake fields, respectively, that don't have a value explicitly indicated.
	 * 
	 * @api
	 */
	public static function fieldsWithProperty( $l, $propUri, $nonFakeDefault=null, $fakeDefault=null ) {
		if( $l instanceof EarthIT_Schema_ResourceClass ) $l = $l->getFields();
		if( !is_array($l) ) throw new Exception("Argument to fieldsWithProperty must be a ResourceClass or a list of fields.");
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
	public static function restAssignableFields( EarthIT_Schema_ResourceClass $rc ) {
		return self::fieldsWithProperty($rc->getFields(), EarthIT_CMIPREST_NS::MAY_BE_SET_VIA_REST_SERVICES, true, false);
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
	
	protected static function patchLike(
		EarthIT_Storage_ItemSaver $storage,
		EarthIT_Schema_ResourceClass $rc,
		$itemId, array $updates, array $saveOptions
	) {
		$itemIdValues = EarthIT_Storage_Util::itemIdToFieldValues($itemId,$rc);
		$updates = EarthIT_Storage_Util::castItemFieldValues($updates,$rc);
		
		$pkUpdates = array();
		$mergedDataWithoutPkChange = self::merge(
			array($itemIdValues, $updates),
			self::ODK_KEEP,
			$pkUpdates);
		
		// 2 step process.
		// - 1) Save the item with any non-pk updates; this will make sure it exists if it doesn't already
		// - 2) If there are any PK updates, apply them.
		// 
		// An update alone would not create nonexistent items.
		
		$itemData = EarthIT_CMIPREST_Util::first( $storage->saveItems(
			array($mergedDataWithoutPkChange), $rc,
			$saveOptions + array(
				EarthIT_Storage_ItemSaver::RETURN_SAVED => true
			)
		) );
		
		if( $pkUpdates ) {
			$itemData = $storage->updateItems(
				$pkUpdates,
				$rc, EarthIT_Storage_ItemFilters::exactFieldValues($itemIdValues, $rc),
				array(EarthIT_Storage_ItemUpdater::RETURN_UPDATED => true)
			);
		}
		
		return $itemData;
	}
	
	public static function putItem( EarthIT_Storage_ItemSaver $storage, EarthIT_Schema_ResourceClass $rc, $itemId, array $itemData ) {
		return self::patchLike( $storage, $rc, $itemId, $itemData, array(
			EarthIT_Storage_ItemSaver::ON_DUPLICATE_KEY => EarthIT_Storage_ItemSaver::ODK_REPLACE
		));
	}
	
	public static function patchItem( EarthIT_Storage_ItemSaver $storage, EarthIT_Schema_ResourceClass $rc, $itemId, array $itemData ) {
		return self::patchLike( $storage, $rc, $itemId, $itemData, array(
			EarthIT_Storage_ItemSaver::ON_DUPLICATE_KEY => EarthIT_Storage_ItemSaver::ODK_UPDATE
		));
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
		} else if( $e instanceof EarthIT_CMIPREST_RequestInvalid ) {
			return EarthIT_CMIPREST_Util::multiErrorResponse( 422, $e->getErrorDetails() );
		} else if( $e instanceof EarthIT_Schema_NoSuchResourceClass ) {
			return EarthIT_CMIPREST_Util::singleErrorResponse( 404, $e->getMessage() );
		} else {
			throw $e;
		}
	}
	
	//// Standard name translations

	/*
	 * collectionName(super duper facility class)  = "super duper facilities"
	 */
	public static function collectionName( EarthIT_Schema_SchemaObject $obj ) {
		return $obj->getFirstPropertyValue(EarthIT_CMIPREST_NS::COLLECTION_NAME) ?:
			EarthIT_Schema_WordUtil::pluralize($obj->getName());
	}
	/*
	 * http://your.domain/api/<collectionPathComponent>/123
	 */
	public static function collectionPathComponent( EarthIT_Schema_SchemaObject $obj ) {
		return EarthIT_Schema_WordUtil::toCamelCase(self::collectionName($obj));
	}
	
	////
	
	public static function encodeItem( array $item, EarthIT_Schema_ResourceClass $rc, EarthIT_CMIPREST_ItemCodec $codec ) {
		return self::first($codec->encodeItems(array($item), $rc));
	}
	public static function decodeItem( array $item, EarthIT_Schema_ResourceClass $rc, EarthIT_CMIPREST_ItemCodec $codec ) {
		return self::first($codec->decodeItems(array($item), $rc));
	}
	
	////
	
	public static function contextUserId( $ctx, $default='123-WHEE-ERROR' ) {
		if( method_exists($ctx,'getUserId') ) return $ctx->getUserId();
		if( method_exists($ctx,'getLoggedInUserId') ) return $ctx->getLoggedInUserId();
		if( $default === '123-WHEE-ERROR' ) throw new Exception("Don't know how to extract user ID from ".self::describe($ctx));
		return $default;
	}
	
	/**
	 * Return an updated context
	 * with the permissions of $suIds (a list of user IDs) added.
	 */
	public static function suContext( $ctx, array $suIds, $pmm ) {
		$currentUserId = self::contextUserId($ctx);
		$includesCurrentUserId = false;
		$addsOtherUserIds = false;
		foreach( $suIds as $suId ) {
			if( $currentUserId === $suId ) {
				$includesCurrentUserId = true;
				// Ha ha ha, nothing to do!
			} else {
				$addsOtherUserIds = true;
			}
		}

		if( $addsOtherUserIds ) {
			throw new Exception("sudoContext not actually implemented in the not-already-the-current-user case. 🐮");
		}
		
		switch( $pmm ) {
		case EarthIT_CMIPREST_RESTAction_SudoAction::PMM_ADD:
			break;
		case EarthIT_CMIPREST_RESTAction_SudoAction::PMM_REPLACE:
			if( !$includesCurrentUserId ) {
				throw new Exception("sudoContext can't remove the current user ID from the context. 🐮");
			}
			break;
		default:
			throw new Exception("Unsupported permission merge mode: $pmm");
		}
		return $ctx;
	}
}
