<?php

class EarthIT_CMIPREST_ResultAssembler_Constant implements EarthIT_CMIPREST_ResultAssembler
{
	protected $value;
	public function __construct($v) {
		$this->value = $v;
	}
	/** @override */
	public function needsResult() {
		return false;
	}
	/** @override */
	public function __invoke( EarthIT_CMIPREST_StorageResult $result ) {
		return $this->value;
	}
}
