<?php

class EarthIT_CMIPREST_MemoryStorage
extends EarthIT_Storage_MemoryStorage
implements EarthIT_CMIPREST_Storage
{
	/** Array of resource class name => list of items of that class */
	protected $items;
	protected $nextId = 1;
	
	
	// TODO: Maybe move these to Util
	
	protected function spMatches( array $item, EarthIT_CMIPREST_SearchParameters $sp ) {
		foreach( $sp->getFieldMatchers() as $fieldName=>$fieldMatcher ) {
			if( !$fieldMatcher->matches($item[$fieldName]) ) return false;
		}
		return true;
	}
	
	protected function order( array $items, array $orderByComponents ) {
		if( count($orderByComponents) == 0 ) return $items;
		
		throw new Exception("MemoryStorage doesn't support ordering when doing johnly searches.");
	}
	
	/** @override */
	public function johnlySearchItems(
		EarthIT_Schema_ResourceClass $rc,
		EarthIT_CMIPREST_SearchParameters $sp,
		array $johnBranches
	) {
		if( count($johnBranches) > 0 ) {
			throw new Exception("John branches not supported by MemoryStorage.");
		}
		
		$results = array();
		if( isset($this->items[$rc->getName()]) ) {
			foreach( $this->items[$rc->getName()] as $item ) {
				if( $item !== null and $this->spMatches($item, $sp) ) {
					$results[] = $item;
				}
			}
		}
		$results = array_slice( $this->order($results, $sp->getOrderByComponents()), $sp->getSkip(), $sp->getLimit() );
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
