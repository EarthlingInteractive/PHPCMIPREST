<?php

class EarthIT_CMIPREST_John {
	public $originResourceClass;
	public $originLinkFields;
	public $targetResourceClass;
	public $targetLinkFields;
	public $targetIsPlural;
	
	public function __construct(
		EarthIT_Schema_ResourceClass $originRc, array $originFields,
		EarthIT_Schema_ResourceClass $targetRc, array $targetFields,
		$targetIsPlural
	) {
		$this->originResourceClass = $originRc; $this->originLinkFields = $originFields;
		$this->targetResourceClass = $targetRc; $this->targetLinkFields = $targetFields;
		$this->targetIsPlural = $targetIsPlural;
	}
	
	public function targetIsPlural() { return $this->targetIsPlural; }
	
	public function __toString() {
		$originFieldNames = array();
		foreach( $this->originLinkFields as $f ) $originFieldNames[] = $f->getName();
		$targetFieldNames = array();
		foreach( $this->targetLinkFields as $f ) $targetFieldNames[] = $f->getName();
		$targetArity = $this->targetIsPlural ? '*' : '1';
		
		return
			$this->originResourceClass->getName()." (".implode(', ',$originFieldNames).") [1] to [$targetArity] ".
			$this->targetResourceClass->getName()." (".implode(', ',$targetFieldNames).")";
	}
}
