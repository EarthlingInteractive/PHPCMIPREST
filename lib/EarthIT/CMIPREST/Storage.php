<?php

// TODO: In next version, do all updates in terms of search parameters
// and don't worry about IDs.  Calling code can translate item-by-ID
// references to searches.
// TODO: Refactor so that functions return StorageResults
// TODO: Build a StorageHelper class that wraps a storage to provide a more human-friendly API
// TODO: Refactor put/post/patch into a single 'save' function with options for
// - allow/disallow/require ID to be generated
// - on duplicate replace/patch/error/undefined
interface EarthIT_CMIPREST_Storage extends
	EarthIT_CMIPREST_JohnlyItemSearcher,
	EarthIT_Storage_ItemSearcher,
	EarthIT_Storage_ItemSaver,
	EarthIT_Storage_ItemDeleter
{
	// TODO: Remove these, relying on inherited methods, instead

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
