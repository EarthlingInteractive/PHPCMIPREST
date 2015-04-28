<?php

class EarthIT_CMIPREST_UserAction_PutItemAction extends EarthIT_CMIPREST_UserAction_ResourceAction
{
	protected $itemData;
	protected $itemId;
	
	public function __construct( $userId, EarthIT_Schema_ResourceClass $resourceClass, $itemId, array $itemData, array $opts=array() ) {
		parent::__construct( $userId, $resourceClass, $opts );
		$this->itemId = $itemId;
		$this->itemData = $itemData;
	}

	public function getItemId() { return $this->itemId; }
	/**
	 * Return the fields being posted in
	 * ~internal form~ (field names match those in schema, values translated to their PHP type)
	 */
	public function getItemData() { return $this->itemData; }
	
	public function getActionDescription() {
		return "put ".$this->getResourceClass()->getName()." with ID='".$this->getItemId()."'";
	}
}
