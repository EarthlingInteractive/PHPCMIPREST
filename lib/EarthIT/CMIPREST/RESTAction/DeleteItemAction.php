<?php

class EarthIT_CMIPREST_RESTAction_DeleteItemAction extends EarthIT_CMIPREST_RESTAction_ResourceAction
{
	protected $itemId;
	
	public function __construct( EarthIT_Schema_ResourceClass $resourceClass, $itemId, EarthIT_CMIPREST_ResultAssembler $rasm ) {
		parent::__construct( $resourceClass, $rasm );
		$this->itemId = $itemId;
	}
	
	public function getItemId() { return $this->itemId; }
	
	public function getActionDescription() {
		return "delete ".$this->getResourceClass()->getName().' with ID="'.$this->getItemId().'"';
	}
}
