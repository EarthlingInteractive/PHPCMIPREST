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
		if( !preg_match( '#^/([^/]+)(?:/(.*))?$#', $path, $bif ) ) {
			throw new Exception("Failed to parse '$path' as a JAO request");
		}

		$req = new EarthIT_CMIPREST_JAORequest();
		$req->method         = $method;
		$req->collectionName = $bif[1];
		$req->instanceId     = isset($bif[2]) ? $bif[2] : null;
		
		return $req;
	}
	
	public static function jaoRequestToUserAction( EarthIT_CMIPREST_JAORequest $req, EarthIT_Schema $schema ) {
		$rc = EarthIT_CMIPREST_Util::getResourceClassByCollectionName($schema, $req->collectionName);
		
		// TODO: Implement all the stuffs
		
		if( $req->method == 'GET' ) {
			if( $req->instanceId === null ) {
				$sp = new EarthIT_CMIPREST_SearchParameters( array(), array(), 0, null );
				return new EarthIT_CMIPREST_UserAction_SearchAction( null, $rc, $sp, array(), array(
					EarthIT_CMIPREST_UserAction::OPT_RESULT_ASSEMBLER =>
						new EarthIT_CMIPREST_ResultAssembler_JAO($schema)
				));
			} else {
				return new EarthIT_CMIPREST_UserAction_GetItemAction( null, $rc, $req->instanceId, array(), array(
					EarthIT_CMIPREST_UserAction::OPT_RESULT_ASSEMBLER =>
						new EarthIT_CMIPREST_ResultAssembler_JAO($schema)
				));
			}
		}
		
		throw new Exception( "Unsupported JAORequest: ".print_r($req,true));
	}
}
