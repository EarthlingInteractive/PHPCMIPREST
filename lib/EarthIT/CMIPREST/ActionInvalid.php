<?php

class EarthIT_CMIPREST_ActionInvalid extends Exception
{
	protected $action;
	protected $errorDetails;
	
	public function __construct( EarthIT_CMIPREST_UserAction $action, array $errorDetails=array() ) {
		$this->action = $action;
		$this->errorDetails = $errorDetails;
		parent::__construct( "Action invalid" );
	}
	
	public function getAction() { return $this->action; }
	public function getErrorDetails() { return $this->errorDetails; }
}
