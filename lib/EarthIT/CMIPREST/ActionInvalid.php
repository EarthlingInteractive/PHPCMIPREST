<?php

class EarthIT_CMIPREST_ActionInvalid extends Exception
{
	protected $action;
	protected $errorDetails;
	
	/**
	 * @param $action The action that was invalid.
	 * @param $errorDetails a list of error structures like
	 *   {
	 *     "class": "ClientError/ActionInvalid",
	 *     "message": "You did it wrong",
	 *     "resourceClassName": "rinsed thingamabob",
	 *     "resourceInstanceId": 37,
	 *     "notes": [ "Your thingamabob is in an invalid state and you should probably reset your database" ]
	 *   }
	 */
	public function __construct( TOGoS_Action $action, array $errorDetails=array(), $code=0, Exception $previous=null ) {
		$this->action = $action;
		$this->errorDetails = $errorDetails;
		$message = isset($errorDetails['message']) ? $errorDetails['message'] : 'Action Invalid';
		parent::__construct( $message, $code, $previous );
	}
	
	public function getAction() { return $this->action; }
	public function getErrorDetails() { return $this->errorDetails; }
}
