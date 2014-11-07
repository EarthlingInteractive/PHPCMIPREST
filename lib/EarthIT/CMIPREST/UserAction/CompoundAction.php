<?php

class EarthIT_CMIPREST_UserAction_CompoundAction extends EarthIT_CMIPREST_UserAction
{
	protected $actions;
	protected $resultExpression;
	
	public function __construct( array $actions, EarthIT_CMIPREST_UserAction_Expression $resultExpression ) {
		$this->actions = $actions;
		$this->resultExpression = $resultExpression;
	}
	
	public function getUserId() {
		throw new Exception("CompoundAction doesn't have a single user ID.  Must look at component action user IDs.");
	}
	
	public function getActions() { return $this->actions; }
	public function getResultExpression() { return $this->resultExpression; }
	
	public function getActionDescription() {
		$lines = array('Compound action');
		// TODO
		return implode("\n", $lines);
	}
}
