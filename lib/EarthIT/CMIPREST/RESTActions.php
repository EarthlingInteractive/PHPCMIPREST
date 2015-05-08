<?php

/**
 * Utility functions for working with RESTActions
 */
class EarthIT_CMIPREST_RESTActions
{
	public static function multiPatch(EarthIT_Schema_ResourceClass $rc, $patches, EarthIT_CMIPREST_ResultAssembler $rasm) {
		$itemPatches = array();
		$resultExpressions = array();
		foreach( $patches as $itemId=>$patchData ) {
			$itemPatches[$itemId] = new EarthIT_CMIPREST_RESTAction_PatchItemAction($rc, $itemId, $patchData, $rasm);
			$resultExpressions[$itemId] = new EarthIT_CMIPREST_RESTAction_ActionResultExpression($itemId);
		}
		return new EarthIT_CMIPREST_RESTAction_CompoundAction(
			$itemPatches,
			new EarthIT_CMIPREST_RESTAction_ArrayExpression($resultExpressions)
		);
	}
	
	public static function multiPost(EarthIT_Schema_ResourceClass $rc, $posts, EarthIT_CMIPREST_ResultAssembler $rasm) {
		$itemPatches = array();
		$resultExpressions = array();
		foreach( $posts as $i => $itemData ) {
			$itemPosts[$i] = new EarthIT_CMIPREST_RESTAction_PostItemAction($rc, $itemData, $rasm);
			$resultExpressions[$i] = new EarthIT_CMIPREST_RESTAction_ActionResultExpression($i);
		}
		return new EarthIT_CMIPREST_RESTAction_CompoundAction(
			$itemPosts,
			new EarthIT_CMIPREST_RESTAction_ArrayExpression($resultExpressions)
		);
	}
	
	public static function compoundAction( array $subActions, EarthIT_CMIPREST_RESTAction_Expression $resultExpression=null ) {
		if( $resultExpression === null ) {
			$resultItems = array();
			foreach( $subActions as $k=>$act ) {
				$resultItems[$k] = new EarthIT_CMIPREST_RESTAction_ActionResultExpression($k);
			}
			$resultExpression = new EarthIT_CMIPREST_RESTAction_ArrayExpression($resultItems);
		}
		return new EarthIT_CMIPREST_RESTAction_CompoundAction( $subActions, $resultExpression );
	}
}
