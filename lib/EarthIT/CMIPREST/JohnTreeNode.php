<?php

class EarthIT_CMIPREST_JohnTreeNode
{
	public $john;
	/** array of key => JohnTreeNode */
	public $branches;
	
	public function __construct( EarthIT_CMIPREST_John $john, array $branches ) {
		$this->john = $john;
		$this->branches = $branches;
	}
	
	public function getJohn() { return $this->john; }
	public function getBranches() { return $this->branches; }
}
