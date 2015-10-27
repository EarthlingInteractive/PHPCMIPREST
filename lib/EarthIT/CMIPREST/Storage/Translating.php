<?php

/**
 * Wraps another Storage, translating items using a provided ItemCodec
 */
class EarthIT_CMIPREST_Storage_Translating implements EarthIT_CMIPREST_Storage
{
	protected $itemCodec;
	protected $backingStorage;
	
	public function __construct( EarthIT_CMIPREST_ItemCodec $itemCodec, EarthIT_CMIPREST_Storage $backingStorage ) {
		$this->itemCodec = $itemCodec;
		$this->backingStorage = $backingStorage;
	}
	
	public function search(
		EarthIT_Schema_ResourceClass $rootRc,
		EarthIT_CMIPREST_SearchParameters $sp,
		array $johnBranches
	) {
		// Probably need to translate search parameters, too `_`
		$result = $this->backingStorage->johnlySearchItems($rootRc, $sp, $johnBranches);
		$translatedResults = array();
		foreach( $result as $path => $items ) {
			$pathComponents = explode('.',$path);
			array_shift($pathComponents); // 'root'
			$rc = $rootRc;
			foreach( $pathComponents as $pc ) {
				$johnTreeNode = $johnBranches[$pc];
				$rc = $johnTreeNode->john->targetResourceClass;
			}
			$translatedResults[$path] = $this->itemCodec->decodeItems($items, $rc);
		}
		return $translatedResults;
	}
	
	public function postItem( EarthIT_Schema_ResourceClass $rc, array $itemData ) {
		return EarthIT_CMIPREST_Util::decodeItem(
			$this->backingStorage->postItem($rc,
				EarthIT_CMIPREST_Util::encodeItem($itemData, $rc, $this->itemCodec)),
			$rc, $this->itemCodec);
	}
	
	public function putItem( EarthIT_Schema_ResourceClass $rc, $itemId, array $itemData ) {
		return EarthIT_CMIPREST_Util::decodeItem(
			$this->backingStorage->putItem($rc, $itemId,
				EarthIT_CMIPREST_Util::encodeItem($itemData, $rc, $this->itemCodec)),
			$rc, $this->itemCodec);
	}
	
	public function patchItem( EarthIT_Schema_ResourceClass $rc, $itemId, array $itemData ) {
		return EarthIT_CMIPREST_Util::decodeItem(
			$this->backingStorage->patchItem($rc, $itemId,
				EarthIT_CMIPREST_Util::encodeItem($itemData, $rc, $this->itemCodec)),
			$rc, $this->itemCodec);
	}
	
	public function deleteItem( EarthIT_Schema_ResourceClass $rc, $itemId ) {
		$this->backingStorage->deleteItem($rc, $itemId);
	}
}
