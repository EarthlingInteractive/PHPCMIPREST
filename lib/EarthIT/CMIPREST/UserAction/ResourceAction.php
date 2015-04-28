<?php

// TODO: Refactor to include only the name of the resource class, not the whole thing.
// including the whole thing makes debug message oogalay
abstract class EarthIT_CMIPREST_UserAction_ResourceAction extends EarthIT_CMIPREST_UserAction
{
	protected $resourceClass;
	
	public function __construct( $userId, EarthIT_Schema_ResourceClass $resourceClass, $opts=array() ) {
		parent::__construct($userId, $opts);
		$this->resourceClass = $resourceClass;
	}
	
	public function getResourceClass() { return $this->resourceClass; }
}
