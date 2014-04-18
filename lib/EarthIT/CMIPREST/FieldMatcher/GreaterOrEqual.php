<?php

class EarthIT_CMIPREST_FieldMatcher_GreaterOrEqual extends EarthIT_CMIPREST_FieldMatcher_BaseComparison
{
	protected function getSqlComparisonOp() { return '>='; }
}
