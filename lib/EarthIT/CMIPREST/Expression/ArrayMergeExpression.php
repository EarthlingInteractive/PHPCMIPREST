<?php

class EarthIT_CMIPREST_Expression_ArrayMergeExpression implements EarthIT_CMIPREST_Expression
{
	protected $itemExpressions;
	public function __construct( array $itemExpressions ) {
		$this->itemExpressions = $itemExpressions;
	}
	public function evaluate( array $context ) {
		$results = array();
		foreach( $this->itemExpressions as $k=>$e ) {
			$results += $e->evaluate($context);
		}
		return $results;
	}
}
