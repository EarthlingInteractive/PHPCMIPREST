<?php

class EarthIT_CMIPREST_FieldMatcher_Equal extends EarthIT_CMIPREST_FieldMatcher_BaseComparison
{
	protected function getSqlComparisonOp() { return '='; }
}
