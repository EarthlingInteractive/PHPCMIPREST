<?php

class EarthIT_CMIPREST_FieldMatcher_GreaterOrEqual extends EarthIT_CMIPREST_FieldMatcher_BaseComparison
{
	protected function getSqlComparisonOp() { return '>='; }
	
	public function matches( $fieldValue ) {
		return $fieldValue >= $this->value;
	}
}
