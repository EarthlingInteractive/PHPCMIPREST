<?php

class EarthIT_CMIPREST_MemoryStorage implements EarthIT_CMIPREST_Storage
{
	/** Array of resource class name => list of items of that class */
	protected $items;
	protected $nextId = 1;
	
	
	// TODO: Maybe move these to Util
	
	protected function matches( array $item, EarthIT_CMIPREST_SearchParameters $sp ) {
		foreach( $sp->getFieldMatchers() as $fieldName=>$fieldMatcher ) {
			if( !$fieldMatcher->matches($item[$fieldName]) ) return false;
		}
		return true;
	}
	
	protected function order( array $items, array $orderByComponents ) {
		// TODO
		return $items;
	}
	
	protected function limit( array $items, $skip, $limit ) {
		// TODO
		return $items;
	}
	
	
	public function search(
		EarthIT_Schema_ResourceClass $rc,
		EarthIT_CMIPREST_SearchParameters $sp,
		array $johnBranches
	) {
		$results = array();
		if( isset($this->items[$rc->getName()]) ) {
			foreach( $this->items[$rc->getName()] as $item ) {
				if( $item !== null and $this->matches($item, $sp) ) {
					$results[] = $item;
				}
			}
		}
		$results = $this->limit( $this->order($results, $sp->getOrderByComponents()), $sp->getSkip(), $sp->getLimit() );
		return array('root'=>$results);
	}
	
	protected function getItemIndex( EarthIT_Schema_ResourceClass $rc, $itemId ) {
		if( !isset($this->items[$rc->getName()]) ) return null;
		
		$sp = EarthIT_CMIPREST_Util::itemIdToSearchParameters($rc, $itemId);
		
		$items = $this->items[$rc->getName()];
		foreach( $items as $k=>$item ) {
			if( $this->matches($item, $sp) ) return $k;
		}
		return null;
	}
	
	/**
	 * Create a new object, returning the values of all it fields after
	 * being created
	 */
	public function postItem( EarthIT_Schema_ResourceClass $rc, array $itemData ) {
		if( ($pk = $rc->getPrimaryKey()) ) {
			if( $pk->getFieldNames() ) foreach( $pk->getFieldNames() as $fn ) {
				if(!isset($itemData[$fn])) $itemData[$fn] = $this->nextId++;
			}
		}
		
		$this->items[$rc->getName()][] = $itemData;
		return $itemData;
	}
	
	/**
	 * Replace all data of an object, setting unspecified fields to their default values,
	 * returning the new values of all its fields
	 */
	public function putItem( EarthIT_Schema_ResourceClass $rc, $itemId, array $itemData ) {
		foreach( EarthIT_CMIPREST_Util::idToFieldValues($rc, $itemId) as $k=>$v ) {
			if(!isset($itemData[$k])) $itemData[$k] = $v;
		}
		if( ($index = $this->getItemIndex($rc, $itemId)) !== null ) {
			$this->items[$rc->getName()][$index] = $itemData;
		} else {
			$this->items[$rc->getName()][] = $itemData;
		}
		
		return $itemData;
	}
	
	/**
	 * Update only specified fields of the given object,
	 * returning the values of all its fields after being updated
	 */
	public function patchItem( EarthIT_Schema_ResourceClass $rc, $itemId, array $itemData ) {
		foreach( EarthIT_CMIPREST_Util::idToFieldValues($rc, $itemId) as $k=>$v ) {
			if(!isset($itemData[$k])) $itemData[$k] = $v;
		}
		if( ($index = $this->getItemIndex($rc, $itemId)) === null ) {
			throw new Exception("No ".$rc->getName()." $itemId to patch!");
		}
		
		$item =& $this->items[$rc->getName()][$index];
		$item = array_merge( $item, $itemData );
		return $item;
	}
	
	/**
	 * Make the item not exist.
	 * Deleting an item that already does not exist should NOT be considered an error.
	 */
	public function deleteItem( EarthIT_Schema_ResourceClass $rc, $itemId ) {
		if( ($index = $this->getItemIndex($rc, $itemId)) !== null ) {
			unset($this->items[$rc->getName()][$index]);
		}
	}
}