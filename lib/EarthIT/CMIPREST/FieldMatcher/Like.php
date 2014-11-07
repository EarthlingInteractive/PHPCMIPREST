<?php

class EarthIT_CMIPREST_FieldMatcher_Like implements EarthIT_CMIPREST_FieldMatcher
{
	protected $pattern;
	protected $percentyPattern;
	
	public function __construct( $pattern ) {
		$this->pattern = $pattern;
		$this->percentyPattern = strtr($pattern, array('_'=>'\\_','%'=>'\\%','*'=>'%'));
	}
	
	public function toSql( $fieldValueSql, $fieldType, &$params ) {
		$paramName = EarthIT_DBC_ParameterUtil::newParamName('matchPattern');
		$params[$paramName] = $this->percentyPattern;
		return "{$fieldValueSql} LIKE {{$paramName}}";
	}
	
	public function matches( $fieldValue ) {
		throw new Exception("Not yet implemented!");
	}
}
