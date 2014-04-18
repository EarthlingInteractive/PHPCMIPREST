<?php

class EarthIT_CMIPREST_FieldMatcher_LesserOrEqual extends EarthIT_CMIPREST_FieldMatcher_BaseComparison
{
	protected function getSqlComparisonOp() { return '<='; }
}
