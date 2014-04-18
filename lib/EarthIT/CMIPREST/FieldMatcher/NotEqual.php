<?php

class EarthIT_CMIPREST_FieldMatcher_NotEqual extends EarthIT_CMIPREST_FieldMatcher_BaseComparison
{
	protected function getSqlComparisonOp() { return '<>'; }
}
