<?php

/**
 * JSONAPI.org-style request
 */
class EarthIT_CMIPREST_JAORequest
{
	public $userId;
	public $method;
	public $collectionName;
	public $instanceId;
	public $pageParams = array();
	public $filterParams = array();
	public $include = array();
	public $fields = array();
	public $sort = array();
	public $content;
	
	public static function parse( $method, $path, $params, $content ) {
		if( !preg_match( '#^/([^/]+)(?:/(.*))?$#', $path, $bif ) ) {
			throw new Exception("Failed to parse '$path' as a JAO request");
		}

		$req = new EarthIT_CMIPREST_JAORequest();
		$req->method         = $method;
		$req->collectionName = $bif[1];
		$req->instanceId     = isset($bif[2]) ? $bif[2] : null;
		$req->content        = $content;
		
		return $req;
	}
	
	protected static function parseValue( $v, EarthIT_Schema_DataType $dt ) {
		return EarthIT_CMIPREST_Util::cast($v, $dt->getPhpTypeName());
	}
	
	protected static function jaoTypeName( EarthIT_Schema_ResourceClass $rc, callable $nameFormatter ) {
		return call_user_func( $nameFormatter,
			$rc->getFirstPropertyValue(EarthIT_CMIPREST_NS::COLLECTION_NAME) ?:
			EarthIT_Schema_WordUtil::pluralize($rc->getName())
		);
	}
	
	protected static function parseContentData(
		array $bod, EarthIT_Schema_ResourceClass $rc,
		EarthIT_Schema $schema, callable $nameFormatter
	) {
		$fieldsByJsonApiName = array();
		foreach( $rc->getFields() as $f ) {
			$fieldsByJsonApiName[call_user_func($nameFormatter, $f->getName())] = $f;
		}
		
		$data = array();
		foreach( $bod as $k=>$v ) {
			if( isset($fieldsByJsonApiName[$k]) ) {
				$f = $fieldsByJsonApiName[$k];
				$data[$f->getName()] = self::parseValue($v, $f->getType());
			} else switch($k) {
			case 'type':
				$expectedTypeName = self::jaoTypeName($rc, $nameFormatter);
				if( $expectedTypeName != $v ) {
					throw new Exception("Type specified in data ('$v') does not match that expected for this URL ('$expectedTypeName')");
				}
				break;
			case 'links':
				//foreach( $v as $thing=>$stuff ) {
				//}
				// Ignore for now
				// TODO: Don't ignore.
			default:
				$rcName = $rc->getName();
				throw new Exception("Unrecognized $rcName attribute: '$k'");
			}
		}
		
		return $data;
	}
	
	public static function jaoRequestToUserAction( EarthIT_CMIPREST_JAORequest $req, EarthIT_Schema $schema, callable $nameFormatter ) {
		$rc = EarthIT_CMIPREST_Util::getResourceClassByCollectionName($schema, $req->collectionName);
		
		// TODO: Implement all the stuffs
		
		$raz = new EarthIT_CMIPREST_ResultAssembler_JAO($schema, $nameFormatter);
		$opts = array( EarthIT_CMIPREST_UserAction::OPT_RESULT_ASSEMBLER => $raz );
		
		switch( $req->method ) {
		case 'GET':
			if( $req->instanceId === null ) {
				$sp = new EarthIT_CMIPREST_SearchParameters(array(), array(), 0, null);
				return new EarthIT_CMIPREST_UserAction_SearchAction($req->userId, $rc, $sp, array(), $opts);
			} else {
				return new EarthIT_CMIPREST_UserAction_GetItemAction($req->userId, $rc, $req->instanceId, array(), $opts);
			}
		case 'POST':
			return new EarthIT_CMIPREST_UserAction_PostItemAction($req->userId, $rc,
				self::parseContentData($req->content['data'], $rc, $schema, $nameFormatter), $opts);
		case 'PUT':
			return new EarthIT_CMIPREST_UserAction_PostItemAction($req->userId, $rc, $req->instanceId,
				self::parseContentData($req->content['data'], $rc, $schema, $nameFormatter), $opts);
		case 'PATCH':
			return new EarthIT_CMIPREST_UserAction_PostItemAction($req->userId, $rc, $req->instanceId,
				self::parseContentData($req->content['data'], $rc, $schema, $nameFormatter), $opts);
		}
		
		throw new Exception( "Unsupported JAORequest: ".print_r($req,true));
	}
}
