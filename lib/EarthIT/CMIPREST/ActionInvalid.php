<?php

/**
 * An action is not valid,
 * either due to contradictions within it,
 * or because of conflicts with the state of the rest of the system.
 *
 * To be represented in an HTTP response,
 * status code 409 should be used.
 */
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
	public function __construct( EarthIT_CMIPREST_Action $action, array $errorDetails=array(), $code=0, Exception|null $previous=null ) {
		$this->action = $action;
		$this->errorDetails = $errorDetails;
		$message = isset($errorDetails['message']) ? $errorDetails['message'] : 'Action Invalid';
		parent::__construct( $message, $code, $previous );
	}
	
	public function getAction() { return $this->action; }
	public function getErrorDetails() { return $this->errorDetails; }
}
