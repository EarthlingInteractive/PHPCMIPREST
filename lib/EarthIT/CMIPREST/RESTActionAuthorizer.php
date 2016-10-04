<?php

/**
 * @api
 */
interface EarthIT_CMIPREST_RESTActionAuthorizer
{
	/**
	 * Can be returned from preAuthorizeSimpleAction for searches
	 * to indicate that the search should be done and the results checked.
	 */
	const AUTHORIZED_IF_RESULTS_VISIBLE = 'if-search-results-visible';
	
	/**
	 * Pass as a search option key mapped to one of the SRVM_* constants
	 * to indicate what to do with unviewable results
	 */
	const SEARCH_RESULT_VISIBILITY_MODE = 'http://ns.earthit.com/CMIPREST/searchResultVisibilityMode';
	/**
	 * Everything's returned unless it includes something you're not allowed
	 * to see, in which case you'll get a 403.
	 * This is the default.
	 */
	const SRVM_BINARY = 'Binary';
	/**
	 * Top-level items that are not readable are hidden.
	 */
	const SRVM_ALLOWED_ONLY = 'AllowedOnly';
	/**
	 * Items that are not readable are hidden,
	 * including nexted items.
	 */
	const SRVM_RECURSIVE_ALLOWED_ONLY = 'RecursiveAllowedOnly';
	
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
