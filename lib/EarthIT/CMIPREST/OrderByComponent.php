<?php

class EarthIT_CMIPREST_OrderByComponent
{
	protected $field;
	protected $ascending;
	
	public function __construct( EarthIT_Schema_Field $field, $ascending=true ) {
		$this->field = $field;
		$this->ascending = $ascending;
	}
	
	public function getField() { return $this->field; }
	public function isAscending() { return $this->ascending; }
}
