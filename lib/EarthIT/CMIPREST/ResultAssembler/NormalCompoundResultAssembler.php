<?php

/**
 * @unstable
 * This is relatively tightly coupled with NOJResultAssembler because
 * the whole CompoundAction thing hasn't been all that thoroughly
 * thought out yet.
 */
class EarthIT_CMIPREST_ResultAssembler_NormalCompoundResultAssembler
extends EarthIT_CMIPREST_ResultAssembler_NOJResultAssembler
implements EarthIT_CMIPREST_ResultAssembler
{
	public function __construct(array $options=array()) {
		parent::__construct(null, $options);
	}
	
	public function needsResult() { return true; } // Otherwise we'd be using a different assembler!
	
	public function assembleResult( EarthIT_CMIPREST_ActionResult $result, TOGoS_Action $action=null, $ctx=null ) {
		$actionResults = array();
		foreach( $result->getAssembledSubActionResults() as $k=>$res ) {
			// If any of the sub-actions failed, we won't even get to this point.
			// This is probably an architectural problem that will require an overhaul of
			// how compound actions work.
			if(
				$res === EarthIT_CMIPREST_ResultAssembler_NOJResultAssembler::SUCCESS or
				$res === EarthIT_CMIPREST_ResultAssembler_NOJResultAssembler::DELETED
			) {
				$actionResults[$k] = array( 'statusCode' => 204 );
			} else {
				$actionResults[$k] = array( 'statusCode' => 200, 'contentObject' => $res );
			}
		}
		return array('actionResults'=>$actionResults);
	}

	public function assembledResultToHttpResponse( $rez, TOGoS_Action $action=null, $ctx=null ) {
		return Nife_Util::httpResponse(200, EarthIT_JSON::encode($rez), 'application/json');
	}
}
