<?php

use EarthIT_CMIPREST_RequestParser_Util AS RPU;
use EarthIT_CMIPREST_RequestParser_ResultAssemblerFactory AS RAF;
use EarthIT_CMIPREST_ResultAssembler_NOJResultAssembler AS NOJRA;

class EarthIT_CMIPREST_RequestParser_CMIPRequestParser implements EarthIT_CMIPREST_RequestParser
{
	protected $schema;
	protected $schemaObjectNamer;
	protected $resultAssemblerFactory;
	
	/**
	 * @param EarthIT_Schema $schema the schema that we're parsing requests for
	 * @param callable $schemaObjectNamer a EarthIT_Schema_Field -> string function
	 *   to provide 'REST names' for fields (probably camelCase).
	 * @param EarthIT_CMIPREST_RequestParser_ResultAssemblerFactory object to use for getting result assemblers.
	 */
	public function __construct( EarthIT_Schema $schema, $schemaObjectNamer, $resultAssemblerFactory=null ) {
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
			$frn = call_user_func($this->schemaObjectNamer, $field, false);
			if( array_key_exists($frn, $restObj) ) {
				$internal[$field->getName()] = $restObj[$frn];
			}
		}
		return $internal;
	}
	
	public function parse( $requestMethod, $path, $queryString, Nife_Blob $content=null ) {
		if( preg_match('#^ /([^/;]+) (?:;([^/]*))? (?:/([^/]*))? (?:/([^/]*))? $#x', $path, $bif) ) {
			$collectionSeg = $bif[1];
			$modifierSeg   = RPU::m($bif, 2);
			$instanceSeg   = RPU::m($bif, 3);
			$propertySeg   = RPU::m($bif, 4);
			
			$resultModifierList = ($modifierSeg == '') ? array() : explode(';',$modifierSeg);
			$collectionModifiers = array();
			foreach( $resultModifierList as $mod ) {
				$kv = explode('=',$mod,2);
				if( count($kv) == 2 ) {
					$collectionModifiers[$kv[0]] = $kv[1];
				} else {
					$collectionModifiers[$mod] = $mod;
				}
			}

			$params = RPU::parseQueryString2($queryString);
			$contentObject = RPU::parseJsonContent($content);
			$filters = array();
			$orderBy = array();
			$skip = 0;
			$limit = null;
			foreach( $params as $p2 ) {
				list($k,$v) = $p2;
				switch( $k ) {
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
					$p = explode(':',$v,2);
					if( count($p) == 2 ) {
						$filters[] = array('fieldName'=>$k, 'opName'=>$p[0], 'pattern'=>$p[1]);
					} else {
						$filters[] = array('fieldName'=>$k, 'opName'=>strpos($v,'*') === false ? 'eq' : 'like', 'pattern'=>$v);
					}
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
				'collectionModifiers' => $collectionModifiers,
				'contentObject' => $contentObject
			);
		}
		return null;
	}
	
	public function toAction( array $request ) {
		if( ($propName = $request['propertyName']) !== null ) {
			throw new Exception("Unrecognized resource property, '$propName'");
		}
		
		if( $request['method'] == 'DO-COMPOUND-ACTION' ) {
			$subActions = array();
			foreach( $request['contentObject']['actions'] as $k=>$cat ) {
				$subRequest = $this->parse(
					$cat['method'], $cat['path'],
					isset($cat['queryString']) ? $cat['queryString'] :
					(isset($cat['params']) ? RPU::buildQueryString($cat['params']) : ''),
					isset($cat['content']) ? $cat['content'] : array()
				);
				$subActions[$k] = $this->toAction($subRequest);
			}
			// TODO: Allow specification of response somehow
			return EarthIT_CMIPREST_RESTActions::compoundAction($subActions);
		}
		
		$resourceClass = EarthIT_CMIPREST_Util::getResourceClassByCollectionName($this->schema, $request['collectionName']);
		
		$rasmOptions = array();
		
		switch( $request['method'] ) {
		case 'GET': case 'HEAD':
			$johnBranches = array();
			foreach( $request['collectionModifiers'] as $k=>$v ) {
				if( $k === 'with' ) {
					$johnBranches = RPU::withsToJohnBranches($this->schema, $resourceClass, $v, $this->schemaObjectNamer);
				} else if( $k === 'keyByIds' ) {
					$rasmOptions[NOJRA::KEY_BY_IDS] = EarthIT_CMIPREST_Util::parseBoolean($v);
				} else {
					throw new Exception("Unrecognized result modifier: '$k'");
				}
			}
			
			if( $itemId = $request['instanceId'] ) {
				return new EarthIT_CMIPREST_RESTAction_GetItemAction(
					$resourceClass, $itemId, $johnBranches,
					$this->resultAssemblerFactory->getResultAssembler(RAF::AC_GET, $rasmOptions)
				);
			} else {
				$fieldsByRestName = RPU::keyByMappedName( $resourceClass->getFields(), $this->schemaObjectNamer );
				$filter     = RPU::parseFilter(     $request['filters'], $resourceClass, $fieldsByRestName );
				$comparator = RPU::parseComparator( $request['orderBy'], $resourceClass, $fieldsByRestName );
				$search = new EarthIT_Storage_Search( $resourceClass, $filter, $comparator, $request['skip'], $request['limit'] );
				return new EarthIT_CMIPREST_RESTAction_SearchAction(
					$search, $johnBranches, array(),
					$this->resultAssemblerFactory->getResultAssembler(RAF::AC_SEARCH, $rasmOptions)
				);
			}
		case 'POST':
			if( $request['instanceId'] !== null ) {
				throw new Exception("You may not include item ID when POSTing");
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
				throw new Exception("You ust include item ID when PUTing");
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
				throw new Exception("You ust include item ID when DELETEing");
			}
			return new EarthIT_CMIPREST_RESTAction_DeleteItemAction(
				$resourceClass, $request['instanceId'],
				$this->resultAssemblerFactory->getResultAssembler(RAF::AC_DELETE, $rasmOptions)
			);
		default:
			throw new Exception("Unrecognized method, '".$request['method']."'");
		}
	}
}
