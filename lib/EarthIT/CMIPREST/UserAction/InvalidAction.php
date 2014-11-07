<?php

class EarthIT_CMIPREST_UserAction_InvalidAction extends EarthIT_CMIPREST_UserAction
{
	protected $errorDetails;
	
	public function __construct( $userId, array $errorDetails ) {
		parent::__construct($userId);
		$this->errorDetails = $errorDetails;
	}
	
	public function getErrorDetails() {
		return $this->errorDetails;
	}
	
	public function getActionDescription() {
		return 'Invalid action';
	}
}
