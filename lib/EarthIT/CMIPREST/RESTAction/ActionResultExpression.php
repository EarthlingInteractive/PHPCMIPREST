<?php

class EarthIT_CMIPREST_RESTAction_ActionResultExpression implements EarthIT_CMIPREST_RESTAction_Expression
{
	protected $index;
	public function __construct( $index ) {
		$this->index = $index;
	}
	public function evaluate( array $stuff ) {
		return $stuff['action results'][$this->index];
	}
}
