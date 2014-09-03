<?php

class EarthIT_CMIPREST_UserAction_PostItemsAction extends EarthIT_CMIPREST_UserAction_ResourceAction
{
	// list of arrays of field name => value
	protected $itemData;
	
	public function __construct( $userId, EarthIT_Schema_ResourceClass $resourceClass, array $itemData ) {
		parent::__construct( $userId, $resourceClass );
		$this->itemData = $itemData;
	}

	/**
	 * Return the fields being posted in
	 * ~internal form~ (field names match those in schema, values translated to their PHP type)
	 */
	public function getItemData() { return $this->itemData; }
	
	public function getActionDescription() {
		return "multi-post to ".$this->getResourceClass()->getName();
	}
}
