<?php

class EarthIT_CMIPREST_RESTAction_PostItemAction extends EarthIT_CMIPREST_RESTAction_ResourceAction
{
	protected $itemData;
	
	public function __construct( EarthIT_Schema_ResourceClass $resourceClass, array $itemData, EarthIT_CMIPREST_ResultAssembler $rasm ) {
		parent::__construct( $resourceClass, $rasm );
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
	
	public function mayBeDestructive() { return true; }
}
