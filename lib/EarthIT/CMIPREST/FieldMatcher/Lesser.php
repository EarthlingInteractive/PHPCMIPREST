<?php

class EarthIT_CMIPREST_FieldMatcher_Lesser extends EarthIT_CMIPREST_FieldMatcher_BaseComparison
{
	protected function getSqlComparisonOp() { return '<'; }
}