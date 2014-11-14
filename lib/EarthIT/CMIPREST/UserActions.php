<?php

/**
 * Utility functions for working with UserActions
 */
class EarthIT_CMIPREST_UserActions
{
	public static function multiPatch($userId, EarthIT_Schema_ResourceClass $rc, $patches) {
		$itemPatches = array();
		$resultExpressions = array();
		foreach( $patches as $itemId=>$patchData ) {
			$itemPatches[$itemId] = new EarthIT_CMIPREST_UserAction_PatchItemAction($userId, $rc, $itemId, $patchData);
			$resultExpressions[$itemId] = new EarthIT_CMIPREST_UserAction_ActionResultExpression($itemId);
		}
		return new EarthIT_CMIPREST_UserAction_CompoundAction(
			$itemPatches,
			new EarthIT_CMIPREST_UserAction_ArrayExpression($resultExpressions)
		);
	}
	
	public static function multiPost($userId, EarthIT_Schema_ResourceClass $rc, $posts) {
		$itemPatches = array();
		$resultExpressions = array();
		foreach( $posts as $i => $itemData ) {
			$itemPosts[$i] = new EarthIT_CMIPREST_UserAction_PostItemAction($userId, $rc, $itemData);
			$resultExpressions[$i] = new EarthIT_CMIPREST_UserAction_ActionResultExpression($i);
		}
		return new EarthIT_CMIPREST_UserAction_CompoundAction(
			$itemPosts,
			new EarthIT_CMIPREST_UserAction_ArrayExpression($resultExpressions)
		);
	}
	
	public static function compoundAction( array $subActions, EarthIT_CMIPREST_UserAction_Expression $resultExpression=null ) {
		if( $resultExpression === null ) {
			$resultItems = array();
			foreach( $subActions as $k=>$act ) {
				$resultItems[$k] = new EarthIT_CMIPREST_UserAction_ActionResultExpression($k);
			}
			$resultExpression = new EarthIT_CMIPREST_UserAction_ArrayExpression($resultItems);
		}
		return new EarthIT_CMIPREST_UserAction_CompoundAction( $subActions, $resultExpression );
	}
}
