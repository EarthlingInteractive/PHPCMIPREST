<?php

/**
 * Static methods to johnly search using plain old ItemSearchers
 */
class EarthIT_CMIPREST_JohnlySearchUtil
{
	/**
	 * This is going to make some really nasty queries.
	 * TODO:
	 * It could be made better just by deduplicating values matched on.
	 * It could be made better yet by making an IN (...) query
	 * in the case where there's only one field to match.
	 */
	protected static function johnToSearch( EarthIT_CMIPREST_John $john, array $originItems ) {
		$z = array();
		foreach( $originItems as $originItem ) {
			$r = array();
			for( $i=0; $i<count($john->originLinkFields); ++$i ) {
				$originField = $john->originLinkFields[$i];
				$targetField = $john->targetLinkFields[$i];
				$r[] = new EarthIT_Storage_Filter_ExactMatchFieldValueFilter(
					$targetField, $john->targetResourceClass, $originItem[$originField->getName()]);
			}
			$z[] = EarthIT_Storage_ItemFilters::anded($r);
		}
		
		return new EarthIT_Storage_Search(
			$john->targetResourceClass,
			EarthIT_Storage_ItemFilters::ored($z));
	}
	
	protected static function _johnlySearchItems(
		EarthIT_Storage_ItemSearcher $IS,
		EarthIT_Storage_Search $search,
		array $johnBranches,
		array &$rez,
		$rezPrefix,
		array $options=array()
	) {
		$items = $IS->searchItems($search, $options);
		$rez[$rezPrefix] = $items;
		foreach( $johnBranches as $k=>$branch ) {
			$subSearch = self::johnToSearch($branch->getJohn(), $items);
			self::_johnlySearchItems($IS, $subSearch, $branch->branches, $rez, "{$rezPrefix}.{$k}", $options);
		}
		return $rez;
	}
	
	public static function johnlySearchItems(
		EarthIT_Storage_ItemSearcher $IS,
		EarthIT_Storage_Search $search,
		array $johnBranches,
		array $options=array()
	) {
		$rez = array();
		self::_johnlySearchItems($IS, $search, $johnBranches, $rez, 'root', $options);
		return $rez;
	}
}
