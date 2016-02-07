<?php

class EarthIT_CMIPREST_Expression_ActionResultExpression implements EarthIT_CMIPREST_Expression
{
	protected $index;
	public function __construct( $index ) {
		$this->index = $index;
	}
	public function evaluate( array $stuff ) {
		if( !array_key_exists($this->index, $stuff['action results']) ) {
			throw new Exception("No action result at index '{$this->index}'.  This ".get_class($this)." is invalid.");
		}
		return $stuff['action results'][$this->index];
	}
}
