<?php

use EarthIT_CMIPREST_RequestParser_Util AS RPU;

class EarthIT_CMIPREST_RequestParser_JAORequestParser implements EarthIT_CMIPREST_RequestParser
{
	protected $schema;
	protected $nameFormatter;

	public function __construct( EarthIT_Schema $schema, callable $nameFormatter ) {
		$this->schema = $schema;
		$this->nameFormatter = $nameFormatter;
	}
	
	public function parse( $method, $path, $queryString, Nife_Blob $content=null ) {
		if( !preg_match( '#^/([^/]+)(?:/(.*))?$#', $path, $bif ) ) {
			throw new Exception("Failed to parse '$path' as a JAO request");
		}
		
		$params = RPU::parseQueryString($queryString);
		
		return array(
			'method' => $method,
			'collectionName' => $bif[1],
			'instanceId' => isset($bif[2]) ? $bif[2] : null,
			'contentObject' => RPU::parseJsonContent($content),
			'pageParams' => isset($params['page']) ? $params['page'] : array()
		);
	}
	
	protected function parseValue( $v, EarthIT_Schema_DataType $dt ) {
		return EarthIT_CMIPREST_Util::cast($v, $dt->getPhpTypeName());
	}
	
	protected function jaoTypeName( EarthIT_Schema_ResourceClass $rc ) {
		return call_user_func( $this->nameFormatter,
			$rc->getFirstPropertyValue(EarthIT_CMIPREST_NS::COLLECTION_NAME) ?:
			EarthIT_Schema_WordUtil::pluralize($rc->getName())
		);
	}
	
	protected function parseContentData(
		array $bod, EarthIT_Schema_ResourceClass $rc
	) {
		$fieldsByJsonApiName = array();
		foreach( $rc->getFields() as $f ) {
			$fieldsByJsonApiName[call_user_func($this->nameFormatter, $f->getName())] = $f;
		}
		
		$refInfoByJsonApiName = array();
		foreach( $rc->getReferences() as $ref ) {
			$jaoName = call_user_func($this->nameFormatter, $ref->getName());
			$tfn = $ref->getTargetFieldNames();
			$ofn = $ref->getOriginFieldNames();
			/** field name to be found in 'linkages' => our (schema-form) field name */
			$fmap = array();
			for( $i=0; $i<count($tfn); ++$i ) {
				$fmap[call_user_func($this->nameFormatter, $tfn)] = $ofn[$i];
			}
			$refInfoByJsonApiName[$jaoName] = array(
				'targetJsonApiTypeName' => self::jaoTypeName($this->schema->getResourceClass($ref->getTargetClassName()), $this->nameFormatter),
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
				case 'id':
					foreach( EarthIT_CMIPREST_Util::idToFieldValues($rc, $v) as $k2=>$v2 ) {
						$item[$k2] = $v2;
					}
					break;
				case 'type':
					$expectedTypeName = self::jaoTypeName($rc, $this->nameFormatter);
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
	
	protected function firstAndOnly( array $stuff ) {
		if( count($stuff) != 1 ) throw new Exception("Not exactly one thing in the stuff, here: ".var_export($stuff,true));
		foreach( $stuff as $thing ) return $thing;
	}
	
	public function toAction( array $req ) {
		$req['userId'] = null; // Blah
		$rc = EarthIT_CMIPREST_Util::getResourceClassByCollectionName($this->schema, $req['collectionName']);
		
		// TODO: Implement all the stuffs
		
		$raz = new EarthIT_CMIPREST_ResultAssembler_JAO($this->schema, $this->nameFormatter);
		$opts = array( EarthIT_CMIPREST_UserAction::OPT_RESULT_ASSEMBLER => $raz );
		
		switch( $req['method'] ) {
		case 'GET':
			if( $req['instanceId'] === null ) {
				$offset = 0;
				$limit = null;
				if( isset($req['pageParams']['number']) and isset($req['pageParams']['size']) ) {
					$limit = $req['pageParams']['size'];
					$offset = $limit * ($req['pageParams']['number']-1);
				}
				$sp = new EarthIT_CMIPREST_SearchParameters(array(), array(), $offset, $limit);
				return new EarthIT_CMIPREST_UserAction_SearchAction($req['userId'], $rc, $sp, array(), $opts);
			} else {
				return new EarthIT_CMIPREST_UserAction_GetItemAction($req['userId'], $rc, $req['instanceId'], array(), $opts);
			}
		case 'POST':
			$items = self::parseContentData($req['contentObject']['data'], $rc, $this->schema, $this->nameFormatter);
			$item = self::firstAndOnly($items);
			// TODO: Allow multi-posts
			return new EarthIT_CMIPREST_UserAction_PostItemAction($req['userId'], $rc, $item, $opts);
		case 'PUT':
			$items = self::parseContentData($req['contentObject']['data'], $rc, $this->schema, $this->nameFormatter);
			$item = self::firstAndOnly($items);
			return new EarthIT_CMIPREST_UserAction_PutItemAction($req['userId'], $rc, $req['instanceId'], $item, $opts);
		case 'PATCH':
			$items = self::parseContentData($req['contentObject']['data'], $rc, $this->schema, $this->nameFormatter);
			$item = self::firstAndOnly($items);
			return new EarthIT_CMIPREST_UserAction_PatchItemAction($req['userId'], $rc, $req['instanceId'], $item, $opts);
		}
		
		throw new Exception( "Unsupported JAORequest: ".print_r($req,true));
	}
}
