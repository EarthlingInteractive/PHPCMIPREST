<?php

class EarthIT_CMIPREST_UserAction_ActionResultExpression implements EarthIT_CMIPREST_UserAction_Expression
{
	protected $index;
	public function __construct( $index ) {
		$this->index = $index;
	}
	public function evaluate( array $context ) {
		return $context['action results'][$this->index];
	}
}
