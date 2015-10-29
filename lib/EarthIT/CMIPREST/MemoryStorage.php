<?php

// TODO: replace this and other CMIPREST_*Storage with a single
// CMIPRESTStorageAdapter that wraps a Storage_Item*er object */
class EarthIT_CMIPREST_MemoryStorage
extends EarthIT_Storage_MemoryStorage
implements EarthIT_CMIPREST_Storage
{
	/** @override */
	public function johnlySearchItems(
		EarthIT_Storage_Search $search,
		array $johnBranches,
		array $options=array()
	) {
		return EarthIT_CMIPREST_JohnlySearchUtil::johnlySearchItems( $this, $search, $johnBranches );
	}
	
	/** @override */
	public function postItem( EarthIT_Schema_ResourceClass $rc, array $itemData ) {
		return EarthIT_CMIPREST_Util::postItem( $this, $rc, $itemData );
	}
	
	/** @override */
	public function putItem( EarthIT_Schema_ResourceClass $rc, $itemId, array $itemData ) {
		return EarthIT_CMIPREST_Util::putItem( $this, $rc, $itemId, $itemData );
	}
	
	/** @override */
	public function patchItem( EarthIT_Schema_ResourceClass $rc, $itemId, array $itemData ) {
		return EarthIT_CMIPREST_Util::patchItem( $this, $rc, $itemId, $itemData );
	}
	
	/** @override */
	public function deleteItem( EarthIT_Schema_ResourceClass $rc, $itemId ) {
		unset($this->items[$rc->getName()][$itemId]);
	}
}
