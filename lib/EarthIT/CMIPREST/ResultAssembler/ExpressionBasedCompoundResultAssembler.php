<?php

/**
 * @unstable
 * This is relatively tightly coupled with NOJResultAssembler because
 * the whole CompoundAction thing hasn't been all that thoroughly
 * thought out yet.
 */
class EarthIT_CMIPREST_ResultAssembler_ExpressionBasedCompoundResultAssembler
extends EarthIT_CMIPREST_ResultAssembler_NOJResultAssembler
implements EarthIT_CMIPREST_ResultAssembler
{
	protected $expression;
	public function __construct(EarthIT_CMIPREST_Expression $expression) {
		parent::__Construct(null,null); // Those won't get used, so who cares.
		$this->expression = $expression;
	}
	
	public function needsResult() { return true; } // Otherwise we'd be using a different assembler!
	
	public function assembleResult( EarthIT_CMIPREST_ActionResult $result, EarthIT_CMIPREST_Action|null $action=null, $ctx=null ) {
		if( !($result instanceof EarthIT_CMIPREST_CompoundActionResult) )
			throw new Exception(get_class($this)." doesn't know how to assemble non-CompoundActionResults");
		
		$subRez = $result->getAssembledSubActionResults();
		return $this->expression->evaluate(array(
			'action results' => $subRez
		));
	}
}
