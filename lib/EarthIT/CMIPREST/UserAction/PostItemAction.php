<?php

class EarthIT_CMIPREST_UserAction_PostItemAction extends EarthIT_CMIPREST_UserAction_ResourceAction
{
	protected $itemdata;
	
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
		return "post to ".$this->getResourceClass()->getName();
	}
}
