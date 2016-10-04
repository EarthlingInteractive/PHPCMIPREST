<?php

use EarthIT_CMIPREST_RequestParser_Util as RPU;
use EarthIT_CMIPREST_RequestParser_ResultAssemblerFactory as RAF;
use EarthIT_CMIPREST_ResultAssembler_NOJResultAssembler as NOJRA;
use EarthIT_CMIPREST_RESTActionAuthorizer as RAA;

class EarthIT_CMIPREST_RequestParser_CMIPRequestParser implements EarthIT_CMIPREST_RequestParser
{
	protected $schema;
	protected $schemaObjectNamer;
	protected $resultAssemblerFactory;
	
	/**
	 * @param EarthIT_Schema $schema the schema that we're parsing requests for
	 * @param EarthIT_Schema_SchemaObjectNamer $schemaObjectNamer namer
	 *   to provide 'REST names' for fields (probably camelCase).
	 * @param EarthIT_CMIPREST_RequestParser_ResultAssemblerFactory object to use for getting result assemblers.
	 */
	public function __construct( EarthIT_Schema $schema, EarthIT_Schema_SchemaObjectNamer $schemaObjectNamer, $resultAssemblerFactory=null ) {
		$this->schema = $schema;
		$this->schemaObjectNamer = $schemaObjectNamer;
		
		if( $resultAssemblerFactory === null ) {
			$resultAssemblerFactory = EarthIT_CMIPREST_RequestParser_CMIPResultAssemblerFactory::getInstance();
		}
		$this->resultAssemblerFactory = $resultAssemblerFactory;
	}
	
	protected function restObjectToInternal( array $restObj, EarthIT_Schema_ResourceClass $rc ) {
		$internal = array();
		foreach( $rc->getFields() as $field ) {
			$frn = $this->schemaObjectNamer->fieldName($field, false, $rc, $this->schema);
			if( array_key_exists($frn, $restObj) ) {
				$internal[$field->getName()] = $restObj[$frn];
			}
		}
		return $internal;
	}
	
	protected static function parseMods($m) {
		$modVals = array();
		foreach( explode(';', $m) as $p ) {
			if( $p == '' ) continue;
			$kv = explode('=', $p, 2);
			if( count($kv) == 2 ) {
				$modVals[$kv[0]] = $kv[1];
			} else {
				$modVals[$p] = $p;
			}
		}
		return $modVals;
	}
	
	public function parse( $requestMethod, $path, $queryString, Nife_Blob $content=null ) {
		if( preg_match('#^ (?P<generalMods> ;[^/]+)? /(?P<collection> [^/;]+) (?:;(?P<collectionMods> [^/]*))? (?:/(?P<instance> [^/]*))? (?:/(?P<property> [^/]*))? $#x', $path, $bif) ) {
			$generalModSeg    = RPU::m($bif, 'generalMods');
			$collectionSeg    = RPU::m($bif, 'collection');
			$collectionModSeg = RPU::m($bif, 'collectionMods');
			$instanceSeg      = RPU::m($bif, 'instance');
			$propertySeg      = RPU::m($bif, 'property');
			
			$generalModifiers    = self::parseMods($generalModSeg);
			$collectionModifiers = self::parseMods($collectionModSeg);
			
			$params = RPU::parseQueryString2($queryString);
			$contentObject = RPU::parseJsonContent($content);
			$filters = array();
			$orderBy = array();
			$skip = 0;
			$limit = null;
			$visibleToUserId = null;
			foreach( $params as $p2 ) {
				list($k,$v) = $p2;
				switch( $k ) {
				case 'visibleToUserId':
					$visibleToUserId = $v;
					break;
				case 'orderBy':
					$p = explode(',', $v);
					foreach( $p as $x ) {
						if( $x[0] == '+' ) {
							$order = 'ASC';
							$x = substr($x,1);
						} else if( $x[0] == '-' ) {
							$order = 'DESC';
							$x = substr($x,1);
						} else {
							$order = 'ASC';
						}
						$orderBy[] = array('fieldName'=>$x, 'direction'=>$order);
					}
					break;
				case 'limit':
					$p = explode(',', $v, 2);
					if( count($p) == 2 ) {
						$skip  = (int)$p[0];
						$limit = (int)$p[1];
					} else {
						$limit = (int)$p[0];
					}
					break;
				default:
					$filters[] = array('fieldName'=>$k, 'pattern'=>$v);
				}
			}
			
			return array(
				'method' => $requestMethod,
				'collectionName' => $collectionSeg,
				'instanceId' => $instanceSeg,
				'propertyName' => $propertySeg,
				'filters' => $filters,
				'skip' => $skip,
				'limit' => $limit,
				'orderBy' => $orderBy,
				'visibleToUserId' => $visibleToUserId,
				'generalModifiers' => $generalModifiers,
				'collectionModifiers' => $collectionModifiers,
				'contentObject' => $contentObject
			);
		}
		return null;
	}
	
	public function toAction( array $request ) {
		if( isset($request['propertyName']) ) {
			throw new EarthIT_CMIPREST_RequestInvalid("Unrecognized resource property, '{$request['propertyName']}'");
		}
		
		$resourceClass = EarthIT_CMIPREST_Util::getResourceClassByCollectionName($this->schema, $request['collectionName']);
		
		$rasmOptions = array();
		
		foreach( $request['generalModifiers'] as $k=>$v ) {
			if( $k === 'keyByIds' ) {
				$rasmOptions[NOJRA::KEY_BY_IDS] = EarthIT_CMIPREST_Util::parseBoolean($v);
			} else {
				throw new EarthIT_CMIPREST_RequestInvalid("Unrecognized general modifier: '$k'");
			}
		}
		
		if( isset($request['collectionModifiers']['keyByIds']) ) {
			$rasmOptions[NOJRA::KEY_BY_IDS] = EarthIT_CMIPREST_Util::parseBoolean(
				$request['collectionModifiers']['keyByIds']
			);
		}
		
		switch( $request['method'] ) {
		case 'GET': case 'HEAD':
			$johnBranches = array();
			foreach( $request['collectionModifiers'] as $k=>$v ) {
				if( $k === 'with' ) {
					$johnBranches = RPU::withsToJohnBranches($this->schema, $resourceClass, $v, $this->schemaObjectNamer);
				} else if( $k === 'keyByIds' ) {
					// Already handled more generally
				} else {
					throw new EarthIT_CMIPREST_RequestInvalid("Unrecognized collection modifier: '$k'");
				}
			}
			
			if( $itemId = $request['instanceId'] ) {
				return new EarthIT_CMIPREST_RESTAction_GetItemAction(
					$resourceClass, $itemId, $johnBranches,
					$this->resultAssemblerFactory->getResultAssembler(RAF::AC_GET, $rasmOptions)
				);
			} else {
				$fieldsByRestName = RPU::keyByMappedName( $resourceClass->getFields(), $this->schemaObjectNamer );
				$filter     = RPU::parseFilter2(    $request['filters'], $resourceClass, $this->schema );
				$comparator = RPU::parseComparator( $request['orderBy'], $resourceClass, $fieldsByRestName );
				$search = new EarthIT_Storage_Search( $resourceClass, $filter, $comparator, $request['skip'], $request['limit'] );
				$searchOptions = array();
				$suIds = array();
				if( $request['visibleToUserId'] ) {
					$searchOptions[RAA::SEARCH_RESULT_VISIBILITY_MODE] = RAA::SRVM_RECURSIVE_ALLOWED_ONLY;
					$suIds[] = $request['visibleToUserId'];
				}
				$searchAct = new EarthIT_CMIPREST_RESTAction_SearchAction(
					$search, $johnBranches, $searchOptions,
					$this->resultAssemblerFactory->getResultAssembler(RAF::AC_SEARCH, $rasmOptions)
				);
				if( !empty($suIds) ) {
					return new EarthIT_CMIPREST_RESTAction_SudoAction($searchAct, $suIds);
				} else {
					return $searchAct;
				}
			}
		case 'POST':
			if( $request['instanceId'] !== null ) {
				throw new EarthIT_CMIPREST_RequestInvalid("You may not include item ID when POSTing");
			}
			$data = $request['contentObject'];
			
			// If all keys are sequential integers (this includes the
			// case when an empty list is posted), then a list of items
			// is being posted.
			// Otherwise, a single item is being posted and will be returned.
			// The multi-item case should be considered the normal one;
			// auto-detecting the single-item case is for backward-compatibility only.
			
			$isSingleItemPost = false;
			$len = count($data);
			for( $i=0; $i<$len; ++$i ) {
				if( !array_key_exists($i, $data) ) {
					$isSingleItemPost = true;
					break;
				}
			}
			
			if( $isSingleItemPost ) {
				return new EarthIT_CMIPREST_RESTAction_PostItemAction(
					$resourceClass,
					$this->restObjectToInternal($data, $resourceClass),
					$this->resultAssemblerFactory->getResultAssembler(RAF::AC_POST, $rasmOptions)
				);
			} else {
				$items = array();
				foreach( $data as $dat ) {
					$items[] = $this->restObjectToInternal($dat, $resourceClass);
				}
				return EarthIT_CMIPREST_RESTActions::multiPost(
					$resourceClass, $items,
					$this->resultAssemblerFactory->getResultAssembler(RAF::AC_MULTIPOST, $rasmOptions)
				);
			}
		case 'PUT':
			if( $request['instanceId'] === null ) {
				throw new EarthIT_CMIPREST_RequestInvalid("You must include item ID when PUTing");
			}
			return new EarthIT_CMIPREST_RESTAction_PutItemAction(
				$resourceClass, $request['instanceId'],
				$this->restObjectToInternal($request['contentObject'], $resourceClass),
				$this->resultAssemblerFactory->getResultAssembler(RAF::AC_PUT, $rasmOptions)
			);
		case 'PATCH':
			if( $request['instanceId'] === null ) {
				// Patch multiple items at once!
				// TODO: Determine if this should exist.  I forgot why it's here or what it's supposed to do.
				$items = array();
				foreach( $request['contentObject'] as $itemId=>$restItem ) {
					$items[$itemId] = $this->restObjectToInternal($restItem, $resourceClass);
				}
				return EarthIT_CMIPREST_RESTActions::multiPatch(
					$resourceClass, $items,
					$this->resultAssemblerFactory->getResultAssembler(RAF::AC_MULTIPATCH, $rasmOptions)
				);
			} else {
				return new EarthIT_CMIPREST_RESTAction_PatchItemAction(
					$resourceClass, $request['instanceId'],
					$this->restObjectToInternal($request['contentObject'], $resourceClass),
					$this->resultAssemblerFactory->getResultAssembler(RAF::AC_PATCH, $rasmOptions)
				);
			}
		case 'DELETE':
			if( $request['instanceId'] === null ) {
				throw new EarthIT_CMIPREST_RequestInvalid("You ust include item ID when DELETEing");
			}
			return new EarthIT_CMIPREST_RESTAction_DeleteItemAction(
				$resourceClass, $request['instanceId'],
				$this->resultAssemblerFactory->getResultAssembler(RAF::AC_DELETE, $rasmOptions)
			);
			// TODO: Handle OPTIONS
		default:
			throw new EarthIT_CMIPREST_RequestInvalid("Unrecognized method, '".$request['method']."'");
		}
	}
}
