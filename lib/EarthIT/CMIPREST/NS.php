<?php

class EarthIT_CMIPREST_NS
{
	const IS_FAKE_FIELD = 'http://ns.nuke24.net/Schema/Application/isFakeField';
	const IS_RETURNED_BY_REST_SERVICES = 'http://ns.nuke24.net/Schema/Application/isReturnedByRestServices';
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
}
