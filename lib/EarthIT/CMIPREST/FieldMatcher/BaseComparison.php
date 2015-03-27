<?php

abstract class EarthIT_CMIPREST_FieldMatcher_BaseComparison implements EarthIT_CMIPREST_FieldMatcher
{
	protected $value;
	
	public function __construct( $value ) {
		$this->value = $value;
	}
	
	public function getValue() {
		return $this->value;
	}
	
	protected abstract function getSqlComparisonOp();
	
	public function toSql( $fieldValueSql, $fieldType, &$params ) {
		$paramName = EarthIT_DBC_ParameterUtil::newParamName('exactMatchValue');
		if( $this->value instanceof EarthIT_DBC_SQLExpression ) {
			$params[$paramName] = $this->value;
		} else {
			// Otherwise it's a literal value
			$params[$paramName] = EarthIT_CMIPREST_Util::cast($this->value, $fieldType);
		}
		$op = $this->getSqlComparisonOp();
		return "{$fieldValueSql} $op {{$paramName}}";
	}
}
