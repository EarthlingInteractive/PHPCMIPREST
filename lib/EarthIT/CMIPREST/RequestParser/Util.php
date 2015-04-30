<?php

class EarthIT_CMIPREST_RequestParser_Util
{
	public static function m( $bif, $idx ) {
		return isset($bif[$idx]) && $bif[$idx] != '' ? $bif[$idx] : null;
	}
	
	public static function parseQueryString( $queryString ) {
		$params = array();
		if($queryString) parse_str($queryString, $params);
		return $params;
	}
	
	public static function parseJsonContent( Nife_Blob $content=null ) {
		if( $content === null ) return null;
		if( $content->getLength() == 0 ) return null;
		if( $content instanceof EarthIT_JSON_PrettyPrintedJSONBlob ) return $content->getValue();
		$c = (string)$content;
		if( $c == '' ) return null;
		return EarthIT_JSON::decode($c);
	}
}
