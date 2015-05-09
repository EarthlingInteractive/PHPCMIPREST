<?php

class EarthIT_CMIPREST_RESTAction_PutItemAction extends EarthIT_CMIPREST_RESTAction_ResourceAction
{
	protected $itemData;
	protected $itemId;
	
	public function __construct( EarthIT_Schema_ResourceClass $resourceClass, $itemId, array $itemData, EarthIT_CMIPREST_ResultAssembler $rasm ) {
		parent::__construct( $resourceClass, $rasm );
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

	public function mayBeDestructive() { return true; }
}
