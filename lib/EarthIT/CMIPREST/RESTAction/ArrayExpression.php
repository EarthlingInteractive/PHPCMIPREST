<?php

class EarthIT_CMIPREST_RESTAction_ArrayExpression implements EarthIT_CMIPREST_RESTAction_Expression
{
	protected $itemExpressions;
	public function __construct( array $itemExpressions ) {
		$this->itemExpressions = $itemExpressions;
	}
	public function evaluate( array $context ) {
		$results = array();
		foreach( $this->itemExpressions as $k=>$e ) {
			$results[$k] = $e->evaluate($context);
		}
		return $results;
	}
}
