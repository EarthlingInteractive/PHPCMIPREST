<?php

abstract class EarthIT_CMIPREST_UserAction_ResourceAction extends EarthIT_CMIPREST_UserAction
{
	protected $resourceClass;
	/** One of the RETURN_* constants, indicating what data the action should return */
	protected $returnFormat;
	
	public function __construct( $userId, EarthIT_Schema_ResourceClass $resourceClass, $opts=array() ) {
		parent::__construct($userId, $opts);
		$this->resourceClass = $resourceClass;
	}
	
	public function getResourceClass() { return $this->resourceClass; }
}
