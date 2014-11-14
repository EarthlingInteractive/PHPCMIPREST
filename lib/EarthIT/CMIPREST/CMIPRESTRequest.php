<?php

/**
 * Represents a request to a REST service.
 * All strings are those given by the client, untranslated.
 * Content, if not null, is already decoded.
 */
class EarthIT_CMIPREST_CMIPRESTRequest
{
	public    $userId;
	protected $method;
	protected $resourceCollectionName;
	protected $resourceInstanceId;
	protected $resourcePropertyName;
	protected $resultModifiers;
	protected $parameters;
	protected $content;
	
	public function __construct( $method, $collectionName, $instanceId, $propertyName, $params, $resultModifiers, $content ) {
		$this->method = $method;
		$this->resourceCollectionName = $collectionName;
		$this->resultModifiers = $resultModifiers;
		$this->resourceInstanceId = $instanceId;
		$this->resourcePropertyName = $propertyName;
		$this->parameters = $params;
		$this->content = $content;
	}
	
	public function getUserId() { return $this->userId; }
	public function getMethod() { return $this->method; }
	public function getResourceCollectionName() { return $this->resourceCollectionName; }
	public function getResourceInstanceId() { return $this->resourceInstanceId; }
	public function getResourcePropertyName() { return $this->resourcePropertyName; }
	public function getResultModifiers() { return $this->resultModifiers; }
	public function getParameters() { return $this->parameters; }
	public function getContent() { return $this->content; }
	
	protected static function m( $bif, $idx ) {
		return isset($bif[$idx]) && $bif[$idx] != '' ? $bif[$idx] : null;
	}
	
	public static function parse( $requestMethod, $path, $params, $content ) {
		if( preg_match('#^ /([^/;]+) (?:;([^/]*))? (?:/([^/]*))? (?:/([^/]*))? $#x', $path, $bif) ) {
			$collectionSeg = $bif[1];
			$modifierSeg   = self::m($bif, 2);
			$instanceSeg   = self::m($bif, 3);
			$propertySeg   = self::m($bif, 4);
			
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
			
			return new static( $requestMethod, $collectionSeg, $instanceSeg, $propertySeg, $params, $resultModifiers, $content );
		} else if( $requestMethod == 'POST' and $path == ';compound' and is_array($content) ) {
			/*$content = array(
				'actions' => array(
					'a' => array(
						'method' => 'GET',
						'path' => '/products',
						'params' => array('orderBy'=>'id'),
						'content' => null
					)
				)
			)*/
			return new static( 'DO-COMPOUND-ACTION', null, null, null, null, null, $content );
		} else {
			return null;
		}
	}
}
