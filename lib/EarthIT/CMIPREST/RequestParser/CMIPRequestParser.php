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
			
			return array(
				'method' => $requestMethod,
				'collectionName' => $collectionSeg,
				'instanceId' => $instanceSeg,
				'propertyName' => $propertySeg,
				'params' => $params,
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
