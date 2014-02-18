<?php

abstract class EarthIT_CMIPREST_UserAction_ResourceAction extends EarthIT_CMIPREST_UserAction
{
	protected $resourceClass;
	
	public function __construct( $userId, EarthIT_Schema_ResourceClass $resourceClass ) {
		parent::__construct($userId);
		$this->resourceClass = $resourceClass;
	}
	
	public function getResourceClass() { return $this->resourceClass; }
}
