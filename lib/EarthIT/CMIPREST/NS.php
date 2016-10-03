<?php

class EarthIT_CMIPREST_NS
{
	const IS_FAKE_FIELD = 'http://ns.nuke24.net/Schema/Application/isFakeField';
	const IS_RETURNED_BY_REST_SERVICES = 'http://ns.nuke24.net/Schema/Application/isReturnedByRestServices';
	const MAY_BE_SET_VIA_REST_SERVICES = 'http://ns.nuke24.net/Schema/Application/mayBeSetViaRestServices';
	const HAS_A_DATABASE_COLUMN = 'http://ns.nuke24.net/Schema/Application/hasADatabaseColumn';
	
	const COLLECTION_NAME = 'http://ns.earthit.com/CMIPREST/collectionName';
	// These only make sense in the context of a reference:
	const INVERSE_NAME = 'http://ns.earthit.com/CMIPREST/inverseName';
	const INVERSE_COLLECTION_NAME = 'http://ns.earthit.com/CMIPREST/inverseCollectionName';
	
	/**
	 * A data structure with the semantics of a list,
	 * but keyed by the identity of the objects in it.
	 * 
	 * This is defined so we can talk about the JSON-encoded objects as
	 * returned by CMIP searches and posts (and sent to PATCH requests).
	 */
	const ID_KEYED_LIST = 'http://ns.earthit.com/CMIPREST/IDKeyedList';
	
	const SEARCH_RESULT_VISIBILITY_MODE = 'http://ns.earthit.com/CMIPREST/searchResultVisibilityMode';
	/**
	 * Everything's returned unless it includes something you're not allowed
	 * to see, in which case you'll get a 403.
	 */
	const SRVM_BINARY = 'Binary';
	/**
	 * Top-level items that are not readable are hidden.
	 */
	const SRVM_ALLOWED_ONLY = 'AllowedOnly';
	/**
	 * Items that are not readable are hidden,
	 * including nexted items.
	 */
	const SRVM_RECURSIVE_ALLOWED_ONLY = 'RecursiveAllowedOnly';
}
