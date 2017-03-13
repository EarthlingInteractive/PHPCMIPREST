<?php

abstract class EarthIT_CMIPREST_RESTAction_ResourceAction extends EarthIT_CMIPREST_RESTAction implements JsonSerializable
{
	protected $resourceClass;
	protected $resultAssembler;
	
	public function __construct( EarthIT_Schema_ResourceClass $resourceClass, EarthIT_CMIPREST_ResultAssembler $rasm ) {
		$this->resourceClass = $resourceClass;
		$this->resultAssembler = $rasm;
	}
	
	public function getResourceClass() { return $this->resourceClass; }
	public function getResultAssembler() { return $this->resultAssembler; }
	
	/** Return true if this action may alter data */
	public abstract function mayBeDestructive();
}
