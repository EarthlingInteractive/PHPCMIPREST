<?php

abstract class EarthIT_CMIPREST_TestCase extends PHPUnit_Framework_TestCase
{
	protected $configDir = 'test/config';
	
	protected function loadTestSchema() {
		return require 'test/schema.php';
	}
}
