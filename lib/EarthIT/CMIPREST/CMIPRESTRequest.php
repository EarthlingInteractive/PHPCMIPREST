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
		$this->resultModifiers = $resultModifiers;
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
			
			return new static( $requestMethod, $collectionSeg, $instanceSeg, $propertySeg, $params, $modifierSeg, $content );
		} else {
			return null;
		}
	}
	
	public function toMethodName() {
		$mods = $this->resultModifiers;
		$inst = $this->resourceInstanceId;
		$prop = $this->resourcePropertyName;
		
		$methodPhrase =
			$this->getMethod() .
			' resource' .
			($mods === null ? '' : ' '.strtr($mods, '-', ' ')) .
			($inst === null ? '' : ' item') .
			($prop === null ? '' : ' '.strtr($prop, '-', ' '));
		return EarthIT_Schema_WordUtil::toCamelCase($methodPhrase);
	}
}
