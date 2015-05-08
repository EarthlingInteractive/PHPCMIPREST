<?php

class EarthIT_CMIPREST_RESTAction_CompoundAction extends EarthIT_CMIPREST_RESTAction
{
	protected $actions;
	protected $resultExpression;
	
	public function __construct( array $actions, EarthIT_CMIPREST_RESTAction_Expression $resultExpression ) {
		$this->actions = $actions;
		$this->resultExpression = $resultExpression;
	}
	
	public function getActions() { return $this->actions; }
	public function getResultExpression() { return $this->resultExpression; }
	
	public function getActionDescription() {
		$lines = array('Compound action');
		foreach( $this->actions as $act ) $lines[] = $act->getActionDescription();
		return implode("\n", $lines);
	}
	
	public function getResultAssembler() {
		throw new Exception(__FUNCTION__." isn't applicable to ".get_class($this));
	}
}
