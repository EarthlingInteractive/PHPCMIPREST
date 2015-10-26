<?php

interface EarthIT_CMIPREST_JohnlyItemFetcher
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
	public function johnlySearch(
		EarthIT_Schema_ResourceClass $rc,
		EarthIT_CMIPREST_SearchParameters $sp,
		array $johnBranches
	);
}
