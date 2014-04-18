<?php

class EarthIT_CMIPREST_FieldMatcher_Greater extends EarthIT_CMIPREST_FieldMatcher_BaseComparison
{
	protected function getSqlComparisonOp() { return '>'; }
}
