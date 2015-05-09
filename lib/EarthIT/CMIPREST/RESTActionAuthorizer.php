<?php

/**
 * @api
 */
interface EarthIT_CMIPREST_RESTActionAuthorizer
{
	const AUTHORIZED_IF_RESULTS_VISIBLE = 'if-search-results-visible';
	
	/**
	 * Will be called to determine whether a simple action (not a
	 * compound one, which is determined based on its components) is
	 * authorized or not.
	 * 
	 * @return true|false|null
	 * 
	 * If it returns false, an explanation about why the action was not
	 * authorized should be appended to the $explanation array.  Notes
	 * may be appended to that array in any case, but the idea is that
	 * if an action is not authorized, that explanation will be shown
	 * to the user.
	 * 
	 * If this returns AUTHORIZED_IF_RESULTS_VISIBLE, that
	 * indicates that the action should be run to get a list of
	 * results, each of which will be checked for visibility, and the
	 * action is authorized if all resulting items are visible.  Of
	 * course this means that the action should not have side-effects.
	 */
	public function preAuthorizeSimpleAction( EarthIT_CMIPREST_RESTAction $act, $ctx, array &$explanation );

	/**
	 * Return true if all items in the list $itemData are visible in
	 * the given context, false otherwise.  Should add explanations on
	 * why some items are not visible to $explanation if returning
	 * false.
	 *
	 * @return boolean
	 */
	public function itemsVisible( array $itemData, EarthIT_Schema_ResourceClass $rc, $ctx, array &$explanation );
}
