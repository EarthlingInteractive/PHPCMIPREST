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
}
