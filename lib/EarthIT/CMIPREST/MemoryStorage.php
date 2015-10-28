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
		if( count($johnBranches) > 0 ) {
			// Though we certainly /could/
			throw new Exception("John branches not supported by MemoryStorage.");
		}
		
		$rc = $search->getResourceClass();
		$filter = $search->getFilter();
		$results = array();
		if( isset($this->items[$rc->getName()]) ) {
			foreach( $this->items[$rc->getName()] as $item ) {
				if( $item !== null and $filter->matches($item) ) $results[] = $item;
			}
		}
		
		usort( $results, $search->getComparator() );
		$results = array_slice( $results, $search->getSkip(), $search->getLimit() );
		return array('root'=>$results);
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
