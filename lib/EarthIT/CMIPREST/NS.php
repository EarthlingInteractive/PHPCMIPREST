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
}
