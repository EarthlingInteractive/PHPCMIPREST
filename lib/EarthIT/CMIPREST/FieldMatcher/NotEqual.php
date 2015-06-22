<?php

class EarthIT_CMIPREST_FieldMatcher_NotEqual extends EarthIT_CMIPREST_FieldMatcher_BaseComparison
{
	public function getSqlComparisonOp() { return '<>'; }
	
	public function matches( $fieldValue ) {
		return $fieldValue != $this->value;
	}
}
