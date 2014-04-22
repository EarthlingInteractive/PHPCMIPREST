<?php

class EarthIT_CMIPREST_FieldMatcher_In implements EarthIT_CMIPREST_FieldMatcher
{
	protected $values;
	
	public function __construct( array $values ) {
		$this->values = $values;
	}
	
	public function toSql( $fieldValueSql, $fieldType, &$params ) {
		if( count($this->values) == 0 ) return 'FALSE';
		$values = array();
		foreach( $this->values as $v ) {
			$values[] = EarthIT_CMIPREST_Util::cast($v, $fieldType);
		}
		$paramName = EarthIT_DBC_ParameterUtil::newParamName('matchValues');
		$params[$paramName] = $values;
		return "{$fieldValueSql} IN {{$paramName}}";
	}
}
