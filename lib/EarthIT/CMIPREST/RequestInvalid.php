<?php

/**
 * Something about the request wasn't right.
 * Maybe a parameter was missing.
 * 
 * Slightly different than ActionInvalid in that the request being invalid
 * is solely a function of the request data and can be determined before
 * attempting to apply it.
 * 
 * If this is thrown while parsing a request,
 * the resulting status code should be 422.
 * 
 * http://stackoverflow.com/questions/3050518/what-http-status-response-code-should-i-use-if-the-request-is-missing-a-required
 */
class EarthIT_CMIPREST_RequestInvalid extends Exception
{
	protected $errorDetails;
	
	public function __construct( $errorDetails, $code=0, Throwable|null $previous=null ) {
		if( is_string($errorDetails) ) {
			$message = $errorDetails;
			$errorDetails = array('message'=>$errorDetails);
		} else if( is_array($errorDetails) ) {
			$message = isset($errorDetails['message']) ? $errorDetails['message'] : "request was invalid";
		}
		parent::__construct($message, $code, $previous);
		$this->errorDetails = $errorDetails;
	}
	
	public function getErrorDetails() { return $this->errorDetails; }
}
