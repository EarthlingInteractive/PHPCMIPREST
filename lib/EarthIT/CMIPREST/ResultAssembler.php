<?php

/**
 * @unstable
 */
interface EarthIT_CMIPREST_ResultAssembler
{
	/**
	 * Return false if this assembler does not actually need an input
	 * to generate its response.  If false, then storage actions that
	 * have no side-effects whose output is used only to generate a
	 * result to be assembled may be skipped (e.g. the retrieval after
	 * a stored item after a POST).
	 */
	public function needsResult();
	
	/**
	 * Assemble a StorageResult into whatever format the thing that's
	 * going to take the results needs.  Normally this will be an array.
	 */
	public function assembleResult( EarthIT_CMIPREST_StorageResult $result );

	/**
	 * TODO: will need something like this
	 * Should it take exceptions?  lists of error data?
	 * Should it return an HTTP response?
	 * @unstable
	 * @param array $errors array of error pseudo-objects.  Each entry
	 * should be an array with information about the error.  A
	 * 'message' field with a string describing the error is usually
	 * expected.
	 */
	//public function assembleErrors( array $errors );

	/**
	 * Take the result returned by assembleResult and encode
	 * it as a Nife_HTTP_Response
	 */
	public function assembledResultToHttpResponse( $assembled );
	
	/**
	 * Encode the fact that an exception occurred as a Nife_HTTP_Response.
	 */
	public static function exceptionToHttpResponse( Exception $e );
}
