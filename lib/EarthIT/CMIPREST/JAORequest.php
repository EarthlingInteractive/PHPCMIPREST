<?php

/**
 * JSONAPI.org-style request
 */
class EarthIT_CMIPREST_JAORequest
{
	public $method;
	public $collectionName;
	public $instanceId;
	public $pageParams = array();
	public $filterParams = array();
	public $include = array();
	public $fields = array();
	public $sort = array();
	
	public static function parse( $method, $path, $params, $content ) {
		if( !preg_match( '#^/([^/]+)(?:/(.*))$#', $path, $bif ) ) {
			throw new Exception("Failed to parse '$path' as a JAO request");
		}
		$collectionName = $bif[1];
		$instanceId     = $bif[2];
		
		return EarthIT_CMIPREST_JAORequest::__set_state(array(
			'method' => $method,
			'collectionName' => $collectionName,
			'instanceId' => $instanceId
		));
	}
	
	public static function jaoRequestToUserAction( EarthIT_CMIPREST_JAORequest $req, EarthIT_Schema $schema ) {
		$rc = EarthIT_CMIPREST_Util::getResourceClassByCollectionName($schema, $req->collectionName);
		
		if( $req->
		
		foreach( $schema->getResourceClasses() as $rc ) {
			$collectionName =
				$rc->getFirstPropertyValue(EarthIT_CMIPREST_NS::COLLECTION_NAME) ?:
				EarthIT_Schema_WordUtil::pluralize($rc->getName());
			if( $dsCollectionName = 
		}
	}
}
