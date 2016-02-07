<?php

class EarthIT_CMIPREST_CompoundActionResult
implements EarthIT_CMIPREST_ActionResult
{
	protected $assembledSubActionResults;
	public function __construct( array $assembledSubActionResults ) {
		$this->assembledSubActionResults = $assembledSubActionResults;
	}
	
	public function getAssembledSubActionResults() {
		return $this->assembledSubActionResults;
	}
}
