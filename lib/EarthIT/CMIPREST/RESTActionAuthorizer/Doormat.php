<?php

/**
 * @api
 * Lets anyone do anything.
 * Useful for testing.
 * Probably shouldn't be used determine permissions on production sites.
 */
class EarthIT_CMIPREST_RESTActionAuthorizer_Doormat implements EarthIT_CMIPREST_RESTActionAuthorizer
{
	public function preAuthorizeSimpleAction( EarthIT_CMIPREST_RESTAction $act, $ctx, array &$explanation ) {
		$explanation[] = "I'm a doormat.";
		return true;
	}
	public function itemsVisible( array $itemData, EarthIT_Schema_ResourceClass $rc, $ctx, array &$explanation ) {
		$explanation[] = "I'm a doormat.";
		return true;
	}
}
