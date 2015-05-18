<?php

use EarthIT_CMIPREST_RequestParser_Util AS RPU;

class EarthIT_CMIPREST_RequestParser_CMIPRequestParser implements EarthIT_CMIPREST_RequestParser
{
	protected $schema;
	protected $schemaObjectNamer;
	protected $keyByIds = true;
	
	public function __construct( EarthIT_Schema $schema, callable $schemaObjectNamer ) {
		$this->schema = $schema;
		$this->schemaObjectNamer = $schemaObjectNamer;
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

			$params = RPU::parseQueryString($queryString);
			$contentObject = RPU::parseJsonContent($content);
			$filters = array();
			$orderBy = array();
			$skip = 0;
			$limit = null;
			foreach( $params as $k=>$v ) {
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
						$limit = (int)$p[1];
					}
					break;
				default:
					$filters[] = array('fieldName'=>$k, 'opName'=>strpos($v,'*') === false ? 'eq' : 'like', 'pattern'=>$v);
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
				'contentObject' => $content
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
		
		if( !$resourceClass->hasRestService() ) {
			throw new EarthIT_CMIPREST_ResourceNotExposedViaService("'".$resourceClass->getName()."' records are not exposed via services");
		}
		
		switch( $request['method'] ) {
		case 'GET': case 'HEAD':
			$johnBranches = array();
			foreach( $request['collectionModifiers'] as $k=>$v ) {
				if( $k === 'with' ) {
					$johnBranches = RPU::withsToJohnBranches($this->schema, $resourceClass, $v, $this->schemaObjectNamer);
				} else {
					throw new Exception("Unrecognized result modifier: '$k'");
				}
			}
			
			if( $itemId = $request['instanceId'] ) {
				return new EarthIT_CMIPREST_RESTAction_GetItemAction(
					$resourceClass, $itemId, $johnBranches,
					new EarthIT_CMIPREST_ResultAssembler_NOJResultAssembler('assembleSingleResult', $this->keyByIds)
				);
			} else {
				$fields = $resourceClass->getFields();
				$fieldRestToInternalNames = array();
				foreach( $fields as $fn=>$field ) {
					$fieldRestToInternalNames[call_user_func($this->schemaObjectNamer,$field)] = $fn;
				}
				
				$fieldMatchers = array();
				$orderBy = RPU::orderByComponents($request['orderBy'], $resourceClass, $this->schemaObjectNamer);
				foreach( $request['filters'] as $filter ) {
					// TODO: 'id' may need to be remapped to multiple field matchers
					// Will probably want to allow for other fake, searchable fields, too
					if( !isset($fieldRestToInternalNames[$filter['fieldName']]) ) {
						throw new Exception("No such field: '{$filter['fieldName']}'");
					}
					$fieldName = $fieldRestToInternalNames[$filter['fieldName']];
					$fieldType = $fields[$fieldName]->getType();
					$fieldMatchers[$fieldName] = RPU::fieldMatcher($filter['opName'], $filter['pattern'], $fieldType);
				}
				$sp = new EarthIT_CMIPREST_SearchParameters( $fieldMatchers, $orderBy, $request['skip'], $request['limit'] );
				return new EarthIT_CMIPREST_RESTAction_SearchAction(
					$resourceClass, $sp, $johnBranches,
					new EarthIT_CMIPREST_ResultAssembler_NOJResultAssembler('assembleSearchResult', $this->keyByIds)
				);
			}
		case 'POST':
			if( $crr->getResourceInstanceId() !== null ) {
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
					$this->restObjectToInternal($resourceClass, $data),
					new EarthIT_CMIPREST_ResultAssembler_NOJResultAssembler('assembleSingleResult', $this->keyByIds)
				);
			} else {
				$items = array();
				foreach( $data as $dat ) {
					$items[] = $this->restObjectToInternal($resourceClass, $dat);
				}
				return EarthIT_CMIPREST_RESTActions::multiPost(
					$resourceClass, $items,
					new EarthIT_CMIPREST_ResultAssembler_NOJResultAssembler('assembleSingleResult', $this->keyByIds)
				);
			}
		case 'PUT':
			if( $request['instanceId'] === null ) {
				throw new Exception("You ust include item ID when PUTing");
			}
			return new EarthIT_CMIPREST_RESTAction_PutItemAction(
				$resourceClass, $request['instanceId'],
				$this->restObjectToInternal($resourceClass, $request['contentObject']),
				new EarthIT_CMIPREST_ResultAssembler_NOJResultAssembler('assembleSingleResult', $this->keyByIds)
			);
		case 'PATCH':
			if( $request['instanceId'] === null ) {
				$items = array();
				foreach( $request['contentObject'] as $itemId=>$restItem ) {
					$items[$itemId] = $this->restObjectToInternal($resourceClass, $restItem);
				}
				return EarthIT_CMIPREST_RESTActions::multiPatch(
					$resourceClass, $items,
					new EarthIT_CMIPREST_ResultAssembler_NOJResultAssembler('assembleSingleResult', $this->keyByIds)
				);
			} else {
				return new EarthIT_CMIPREST_RESTAction_PatchItemAction(
					$resourceClass, $request['instanceId'],
					$this->restObjectToInternal($resourceClass, $request['contentObject']),
					new EarthIT_CMIPREST_ResultAssembler_NOJResultAssembler('assembleSingleResult', $this->keyByIds)
				);
			}
		case 'DELETE':
			if( $request['instanceId'] === null ) {
				throw new Exception("You ust include item ID when DELETEing");
			}
			return new EarthIT_CMIPREST_RESTAction_DeleteItemAction(
				$userId, $resourceClass, $request['instanceId'],
				new EarthIT_CMIPREST_ResultAssembler_NOJResultAssembler('assembleDeleteResult', $this->keyByIds)
			);
		default:
			throw new Exception("Unrecognized method, '".$crr->getMethod()."'");
		}
	}
}
