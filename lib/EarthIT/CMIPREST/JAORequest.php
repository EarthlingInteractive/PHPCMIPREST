<?php

/**
 * JSONAPI.org-style request
 * 
 * TODO: Move functionality to, like, RequestParser/JAORequestParser
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
		$req->pageParams     = isset($params['page']) ? $params['page'] : array();
		
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
		
		$refInfoByJsonApiName = array();
		foreach( $rc->getReferences() as $ref ) {
			$jaoName = call_user_func($nameFormatter, $ref->getName());
			$tfn = $ref->getTargetFieldNames();
			$ofn = $ref->getOriginFieldNames();
			/** field name to be found in 'linkages' => our (schema-form) field name */
			$fmap = array();
			for( $i=0; $i<count($tfn); ++$i ) {
				$fmap[call_user_func($nameFormatter, $tfn)] = $ofn[$i];
			}
			$refInfoByJsonApiName[$jaoName] = array(
				'targetJsonApiTypeName' => self::jaoTypeName($schema->getResourceClass($ref->getTargetClassName()), $nameFormatter),
				'33fmap' => $fmap,
			);
		
		}
		
		// ^ It might be nice to cache all that stuff somewhere in case
		// we're doing a lot of these.
		
		$isList = true;
		for( $i=0; $i<count($bod); ++$i ) {
			if( !array_key_exists($i, $bod) ) $isList = false;
		}
		
		$bods = $isList ? $bod : array($bod);
		
		// So, uhm, this provides no way to update 1-many (where the
		// object in question is the one) relationships.
		// The semantics on what those would mean are, I suppose, documented
		// at http://jsonapi.org/format/#crud-updating-to-many-relationships
		
		$items = array();
		foreach( $bods as $bod ) {
			$item = array();
			foreach( $bod as $k=>$v ) {
				if( isset($fieldsByJsonApiName[$k]) ) {
					$f = $fieldsByJsonApiName[$k];
					$item[$f->getName()] = self::parseValue($v, $f->getType());
				} else switch($k) {
				case 'type':
					$expectedTypeName = self::jaoTypeName($rc, $nameFormatter);
					if( $expectedTypeName != $v ) {
						throw new Exception("Type specified in data ('$v') does not match that expected for this URL ('$expectedTypeName')");
					}
					break;
				case 'links':
					foreach( $v as $thing=>$stuff ) {
						// TODO: Throw a 403? http://jsonapi.org/format/#crud-updating-resource-relationships
						if( !isset($refInfoByJsonApiName[$thing]) ) continue; // Ignore the unrecognized links
						$refInfo = $refInfoByJsonApiName[$thing];								
						foreach( $stuff['linkage'] as $fmapKey => $v ) {
							if( $fmapKey == 'type' ) {
								if( $v != $refInfo['targetJsonApiTypeName'] ) {
									throw new Exception("Type specified in linkage ('$v') does not match that expected for '$thing' ('{$refInfo['targetJsonApiTypeName']}')");
								}
							} else {
								$item[$refInfo['33fmap'][$fmapKey]] = $v;
							}
						}
					}
					break;
				default:
					$rcName = $rc->getName();
					throw new Exception("Unrecognized $rcName attribute: '$k'");
				}
			}
			$items[] = $item;
		}
		
		return $items;
	}
	
	protected static function firstAndOnly( array $stuff ) {
		if( count($stuff) != 1 ) throw new Exception("Not exactly one thing in the stuff, here: ".var_export($stuff,true));
		foreach( $stuff as $thing ) return $thing;
	}
	
	public static function jaoRequestToUserAction( EarthIT_CMIPREST_JAORequest $req, EarthIT_Schema $schema, callable $nameFormatter ) {
		$rc = EarthIT_CMIPREST_Util::getResourceClassByCollectionName($schema, $req->collectionName);
		
		// TODO: Implement all the stuffs
		
		$raz = new EarthIT_CMIPREST_ResultAssembler_JAO($schema, $nameFormatter);
		$opts = array( EarthIT_CMIPREST_UserAction::OPT_RESULT_ASSEMBLER => $raz );
		
		switch( $req->method ) {
		case 'GET':
			if( $req->instanceId === null ) {
				$offset = 0;
				$limit = null;
				if( isset($req->pageParams['number']) and isset($req->pageParams['size']) ) {
					$limit = $req->pageParams['size'];
					$offset = $limit * ($req->pageParams['number']-1);
				}
				$sp = new EarthIT_CMIPREST_SearchParameters(array(), array(), $offset, $limit);
				return new EarthIT_CMIPREST_UserAction_SearchAction($req->userId, $rc, $sp, array(), $opts);
			} else {
				return new EarthIT_CMIPREST_UserAction_GetItemAction($req->userId, $rc, $req->instanceId, array(), $opts);
			}
		case 'POST':
			$items = self::parseContentData($req->content['data'], $rc, $schema, $nameFormatter);
			$item = self::firstAndOnly($items);
			// TODO: Allow multi-posts
			return new EarthIT_CMIPREST_UserAction_PostItemAction($req->userId, $rc, $item, $opts);
		case 'PUT':
			$items = self::parseContentData($req->content['data'], $rc, $schema, $nameFormatter);
			$item = self::firstAndOnly($items);
			return new EarthIT_CMIPREST_UserAction_PutItemAction($req->userId, $rc, $req->instanceId, $item, $opts);
		case 'PATCH':
			$items = self::parseContentData($req->content['data'], $rc, $schema, $nameFormatter);
			$item = self::firstAndOnly($items);
			return new EarthIT_CMIPREST_UserAction_PatchItemAction($req->userId, $rc, $req->instanceId, $item, $opts);
		}
		
		throw new Exception( "Unsupported JAORequest: ".print_r($req,true));
	}
}
