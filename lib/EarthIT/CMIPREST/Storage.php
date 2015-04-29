<?php

// TODO: In next version, do all updates in terms of search parameters
// and don't worry about IDs.  Calling code can translate item-by-ID
// references to searches.
// TODO: Refactor so that functions return StorageResults
// TODO: Build a StorageHelper class that wraps a storage to provide a more human-friendly API
// TODO: Refactor put/post/patch into a single 'save' function with options for
// - allow/disallow/require ID to be generated
// - on duplicate replace/patch/error/undefined
interface EarthIT_CMIPREST_Storage
{
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
	 * @param array $johnBranches array of path component =>
	 *   EarthIT_CMIPREST_JohnTreeNode (each JohnTreeNode has its own
	 *   branches)
	 * @return array of result object lists, keyed by branch path
	 */
	public function search(
		EarthIT_Schema_ResourceClass $rc,
		EarthIT_CMIPREST_SearchParameters $sp,
		array $johnBranches
	);
	
	/**
	 * Create a new object, returning the values of all it fields after
	 * being created
	 */
	public function postItem( EarthIT_Schema_ResourceClass $rc, array $itemData );
	
	/**
	 * Replace all data of an object, setting unspecified fields to their default values,
	 * returning the new values of all its fields
	 */
	public function putItem( EarthIT_Schema_ResourceClass $rc, $itemId, array $itemData );
	
	/**
	 * Update only specified fields of the given object,
	 * returning the values of all its fields after being updated
	 */
	public function patchItem( EarthIT_Schema_ResourceClass $rc, $itemId, array $itemData );
	
	/**
	 * Make the item not exist.
	 * Deleting an item that already does not exist should NOT be considered an error.
	 */
	public function deleteItem( EarthIT_Schema_ResourceClass $rc, $itemId );
}
