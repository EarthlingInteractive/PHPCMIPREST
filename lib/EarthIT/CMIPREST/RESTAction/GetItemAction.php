<?php

class EarthIT_CMIPREST_RESTAction_GetItemAction extends EarthIT_CMIPREST_RESTAction_ResourceAction
{
	protected $itemId;
	protected $johnBranches;
	
	public function __construct( EarthIT_Schema_ResourceClass $resourceClass, $itemId, array $johnBranches, EarthIT_CMIPREST_ResultAssembler $rasm ) {
		parent::__construct( $resourceClass, $rasm );
		$this->itemId = $itemId;
		$this->johnBranches = $johnBranches;
	}
	
	public function getItemId() { return $this->itemId; }
	public function getJohnBranches() { return $this->johnBranches; }
	
	public function getActionDescription() {
		return "get ".$this->getResourceClass()->getName().' with ID="'.$this->getItemId().'"';
	}
	
	public function mayBeDestructive() { return false; }
}
