<?php

use EarthIT_CMIPREST_RequestParser_Util AS RPU;

class EarthIT_CMIPREST_RequestParser_CompoundRequestParser
implements EarthIT_CMIPREST_RequestParser
{
	protected $subRequestParser;
	public function __construct( EarthIT_CMIPREST_RequestParser $subRequestParser ) {
		$this->subRequestParser = $subRequestParser;
	}
	
	public function parse( $requestMethod, $path, $queryString, Nife_Blob $content=null ) {
		if( $path == '' and ($requestMethod == 'DO-COMPOUND-ACTION' or $requestMethod == 'POST') ) {
			return array(
				'method' => 'DO-COMPOUND-ACTION',
				'contentObject' => RPU::parseJsonContent($content)
			);
		}
		return null;
	}
	
	public function toAction( array $request ) {
		$subActions = array();
		foreach( $request['contentObject']['actions'] as $k=>$cat ) {
			$subRequest = $this->subRequestParser->parse(
				$cat['method'], $cat['path'],
				isset($cat['queryString']) ? $cat['queryString'] :
				(isset($cat['params']) ? RPU::buildQueryString($cat['params']) : ''),
				Nife_Util::blob(isset($cat['content']) ? $cat['content'] : '')
			);
			$subActions[$k] = $this->subRequestParser->toAction($subRequest);
		}
		// TODO: Somehow allow specification of:
		// - whether the entire thing should be done as a transaction
		// - the result expression
		return EarthIT_CMIPREST_RESTActions::compoundAction($subActions);
	}
}
