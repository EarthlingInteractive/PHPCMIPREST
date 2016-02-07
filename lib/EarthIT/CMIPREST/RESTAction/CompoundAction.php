<?php

class EarthIT_CMIPREST_RESTAction_CompoundAction extends EarthIT_CMIPREST_RESTAction
{
	protected $actions;
	protected $resultAssembler;
	
	public function __construct( array $actions, EarthIT_CMIPREST_ResultAssembler $resultAssembler ) {
		$this->actions = $actions;
		$this->resultAssembler = $resultAssembler;
	}
	
	public function getActions() { return $this->actions; }
	
	public function getActionDescription() {
		$lines = array('Compound action');
		foreach( $this->actions as $act ) $lines[] = $act->getActionDescription();
		return implode("\n", $lines);
	}
	
	public function getResultAssembler() {
		return $this->resultAssembler;
	}
}
