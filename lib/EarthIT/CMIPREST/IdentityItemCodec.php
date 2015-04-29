<?php

class EarthIT_CMIPREST_IdentityItemCodec implements EarthIT_CMIPREST_ItemCodec
{
	public static function getInstance() { return new self; }
	
	public function encodeItems( array $items, EarthIT_Schema_ResourceClass $rc ) { return $items; }
	public function decodeItems( array $items, EarthIT_Schema_ResourceClass $rc ) { return $items; }
}
