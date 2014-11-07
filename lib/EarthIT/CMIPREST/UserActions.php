<?php

/**
 * Utility functions for working with UserActions
 */
class EarthIT_CMIPREST_UserActions
{
	public static function multiPatch($userId, EarthIT_Schema_ResourceClass $rc, $patches) {
		$itemPatches = array();
		$resultExpressions = array();
		$i = 0;
		foreach( $patches as $itemId=>$patchData ) {
			$itemPatches[$i] = new EarthIT_CMIPREST_UserAction_PatchItemAction($userId, $rc, $itemId, $patchData);
			$resultExpressions[$i] = new EarthIT_CMIPREST_UserAction_ActionResultExpression($i);
			++$i;
		}
		return new EarthIT_CMIPREST_UserAction_CompoundAction(
			$itemPatches,
			new EarthIT_CMIPREST_UserAction_ArrayExpression($resultExpressions)
		);
	}
	
	public static function multiPost($userId, EarthIT_Schema_ResourceClass $rc, $posts) {
		$itemPatches = array();
		$resultExpressions = array();
		$i = 0;
		foreach( $posts as $itemId=>$itemData ) {
			$itemPosts[$i] = new EarthIT_CMIPREST_UserAction_PostItemAction($userId, $rc, $itemData);
			$resultExpressions[$i] = new EarthIT_CMIPREST_UserAction_ActionResultExpression($i);
			++$i;
		}
		return new EarthIT_CMIPREST_UserAction_CompoundAction(
			$itemPosts,
			new EarthIT_CMIPREST_UserAction_ArrayExpression($resultExpressions)
		);
	}
}
