<?php

interface EarthIT_CMIPREST_JohnlyItemSearcher
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
	 * @param EarthIT_Storage_Search $search
	 * @param array $johnBranches array of path component =>
	 *   EarthIT_CMIPREST_JohnTreeNode (each JohnTreeNode has its own
	 *   branches)
	 * @param array $options additional search options (same as taken by ItemSearcher#searchItems)
	 * @return array of result object lists, keyed by branch path
	 */
	public function johnlySearchItems(
		EarthIT_Storage_Search $search,
		array $johnBranches,
		array $options=array()
	);
}
