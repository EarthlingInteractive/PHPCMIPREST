<?php

interface EarthIT_CMIPREST_Storage
{
	/**
	 * Return a single object (in internal form)
	 * from the given resource class with the given ID,
	 * or null if no such object exists
	 */
	public function getItem( EarthIT_Schema_ResourceClass $rc, $itemId );
	
	/**
	 * Perform a search, returning an array of join path => list of result objects (in internal form).
	 * Join path is 'root' for the root object set, and "{$parentPath}.{$branchKey}"
	 * for objects associated by sub-branches.
	 *
	 * e.g. might return
	 * array(
	 *   'root' => array(
	 *     array(
	 *   ),
	 *   'root.accounts' => array(
	 *     array('accountId'=>1, 'personId'=>1),
	 *     array('accountId'=>2, 'personId'=>1),
	 *     array('accountId'=>3, 'personId'=>2),
	 *     array('accountId'=>4, 'personId'=>2),
	 *   )
	 * )
	 *
	 * @param EarthIT_Schema_ResourceClass $rc
	 * @param EarthIT_CMIPREST_SearchParameters $sp
	 * @param array $johnBranches array of EarthIT_CMIPREST_JohnTreeNode
	 * @return array of result object lists, keyed by branch path
	 */
	public function search(
		EarthIT_Schema_ResourceClass $rc,
		EarthIT_CMIPREST_SearchParameters $sp,
		array $johnBranches
	);
	
	/**
	 * Create a new object, returning its ID
	 */
	public function postItem( EarthIT_Schema_ResourceClass $rc, array $itemData );
	
	/**
	 * Replace all data of an object, setting unspecified fields to their default values
	 */
	public function putItem( EarthIT_Schema_ResourceClass $rc, $itemId, array $itemData );
	
	/**
	 * Update only specified fields of the given object
	 */
	public function patchItem( EarthIT_Schema_ResourceClass $rc, $itemId, array $itemData );
}
