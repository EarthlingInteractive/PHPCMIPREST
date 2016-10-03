<?php

/**
 * @api
 */
interface EarthIT_CMIPREST_RESTActionAuthorizer2
extends EarthIT_CMIPREST_RESTActionAuthorizer
{
	/**
	 * Return a subset of the given items
	 * that are visible in the given context.
	 */
	public function visibleItems( array $itemData, EarthIT_Schema_ResourceClass $rc, $ctx, array &$explanation );
}
