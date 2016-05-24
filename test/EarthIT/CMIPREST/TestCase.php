<?php

abstract class EarthIT_CMIPREST_TestCase extends PHPUnit_Framework_TestCase
{
	protected $configDir = 'test/config';
	
	protected function loadTestSchema() {
		return require 'test/schema.php';
	}
	
	protected function newEntityId() {
		return bcadd( '50000000000', mt_rand() );
	}
	
	protected $cache = array();
	protected function __get($thing) {
		if( isset($this->cache[$thing]) ) return $this->cache[$thing];
		if( $thing == 'schema' ) {
			return $this->cache[$thing] = $this->loadTestSchema();
		}
		throw new Exception("Unknown thing: $thing");
	}
	
	protected function rc($rc) {
		if( $rc instanceof EarthIT_Schema_ResourceClass ) return $rc;
		
		$rcName = $rc;
		$rc = $this->schema->getResourceClass($rcName);
		if( $rc === null ) throw new Exception("No such resource class $rcName");
		return $rc;
	}
}
