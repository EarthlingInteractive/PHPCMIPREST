<?php

class EarthIT_CMIPREST_ResultAssembler_CSVResultAssembler implements EarthIT_CMIPREST_ResultAssembler
{
	public function needsResult() { return true; }
	
	/**
	 * Assemble a StorageResult into whatever format the thing that's
	 * going to take the results needs.  Normally this will be an array.
	 * 
	 * @param EarthIT_CMIPREST_ActionResult $result the return value of the action
	 * @param EarthIT_CMIPREST_Action $action the action that was invoked to get this result
	 * @param mixed $ctx some value representing the context in which the action was done
	 */
	public function assembleResult( EarthIT_CMIPREST_ActionResult $result, EarthIT_CMIPREST_Action|null $action=null, $ctx=null ) {
		$rootRc = $result->getRootResourceClass();
		$columnHeaders = array();
		foreach( $rootRc->getFields() as $fn=>$field ) {
			$columnHeaders[] = $fn;
		}

		$itemCollections = $result->getItemCollections();
		$rows = array($columnHeaders);
		foreach( $itemCollections['root'] as $item ) {
			$row = array();
			foreach( $columnHeaders as $c ) {
				$row[$c] = $item[$c];
			}
			$rows[] = $row;
		}
		return $rows;
	}

	/**
	 * Take the result returned by assembleResult and encode
	 * it as a Nife_HTTP_Response
	 */
	public function assembledResultToHttpResponse( $assembled, EarthIT_CMIPREST_Action|null $action=null, $ctx=null ) {
		return Nife_Util::httpResponse(200, new EarthIT_CMIPREST_CSVBlob($assembled), array('content-type'=>'text/csv'));
	}
	
	/**
	 * Encode the fact that an exception occurred as a Nife_HTTP_Response.
	 */
	public function exceptionToHttpResponse( Exception $e, EarthIT_CMIPREST_Action|null $action=null, $ctx=null ) {
		$userIsAuthenticated = ($ctx and method_exists($ctx,'userIsAuthenticated')) ? $ctx->userIsAuthenticated() : false;
		return EarthIT_CMIPREST_Util::exceptionalNormalJsonHttpResponse($e, $userIsAuthenticated, array(
			EarthIT_CMIPREST_Util::BASIC_WWW_AUTHENTICATION_REALM => $this->basicWwwAuthenticationRealm
		));
	}
}
