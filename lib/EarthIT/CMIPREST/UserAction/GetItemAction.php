<?php

class EarthIT_CMIPREST_UserAction_GetItemAction extends EarthIT_CMIPREST_UserAction_ResourceAction
{
	protected $itemId;
	protected $johnBranches;
	
	public function __construct( $userId, EarthIT_Schema_ResourceClass $resourceClass, $itemId, array $johnBranches ) {
		parent::__construct( $userId, $resourceClass );
		$this->itemId = $itemId;
		$this->johnBranches = $johnBranches;
	}
	
	public function getItemId() { return $this->itemId; }
	public function getJohnBranches() { return $this->johnBranches; }
	
	public function getActionDescription() {
		return "get ".$this->getResourceClass()->getName().' with ID="'.$this->getItemId().'"';
	}
}
