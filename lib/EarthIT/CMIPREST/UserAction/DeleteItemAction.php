<?php

class EarthIT_CMIPREST_UserAction_DeleteItemAction extends EarthIT_CMIPREST_UserAction_ResourceAction
{
	protected $itemId;
	
	public function __construct( $userId, EarthIT_Schema_ResourceClass $resourceClass, $itemId ) {
		parent::__construct( $userId, $resourceClass );
		$this->itemId = $itemId;
	}
	
	public function getItemId() { return $this->itemId; }
	
	public function getActionDescription() {
		return "delete ".$this->getResourceClass()->getName().' with ID="'.$this->getItemId().'"';
	}
}
