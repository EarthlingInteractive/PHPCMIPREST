<?php

use EarthIT_CMIPREST_RequestParser_Util AS RPU;

class EarthIT_CMIPREST_RequestParser_CMIPRequestParser implements EarthIT_CMIPREST_RequestParser
{
	public function parse( $requestMethod, $path, $queryString, Nife_Blob $content=null ) {
		if( preg_match('#^ /([^/;]+) (?:;([^/]*))? (?:/([^/]*))? (?:/([^/]*))? $#x', $path, $bif) ) {
			$collectionSeg = $bif[1];
			$modifierSeg   = RPU::m($bif, 2);
			$instanceSeg   = RPU::m($bif, 3);
			$propertySeg   = RPU::m($bif, 4);
			
			$resultModifierList = ($modifierSeg == '') ? array() : explode(';',$modifierSeg);
			$resultModifiers = array();
			foreach( $resultModifierList as $mod ) {
				$kv = explode('=',$mod,2);
				if( count($kv) == 2 ) {
					$resultModifiers[$kv[0]] = $kv[1];
				} else {
					$resultModifiers[$mod] = $mod;
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
					$filters[] = array('fieldName'=>$k, 'opName'=>'eq', 'value'=>$v);
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
				'resultModifiers' => $resultModifiers,
				'contentObject' => $content
			);
		}
		return null;
	}
	
	public function toAction( array $request ) {
		throw new Exception("Not yet implemented");
	}
}
