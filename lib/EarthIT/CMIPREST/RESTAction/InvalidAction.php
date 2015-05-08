<?php

/**
 * An action class whose sole purpose is to not be valid.
 * This is here so we can test that unrecognized actions get handled properly.
 */
class EarthIT_CMIPREST_RESTAction_InvalidAction extends EarthIT_CMIPREST_RESTAction
{
	protected $errorDetails;
	protected $resultAssembler;

	public function __construct( array $errorDetails, EarthIT_CMIPREST_ResultAssembler $rasm ) {
		$this->errorDetails = $errorDetails;
		$this->resultAssembler = $rasm;
	}
	
	public function getErrorDetails() { return $this->errorDetails; }
	public function getActionDescription() { return 'Invalid action'; }
	public function getResultAssembler() { return $this->resultAssembler; }
}
