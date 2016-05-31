<?php

/**
 * Utility functions for working with RESTActions
 */
class EarthIT_CMIPREST_RESTActions
{
	public static function multiPatch(EarthIT_Schema_ResourceClass $rc, $patches, EarthIT_CMIPREST_ResultAssembler $rasm) {
		// Warning:
		// This will only work properly when:
		// - result items have IDs and
		// - the result assembler returns arrays of key => item data
		// Otherwise an ArrayMergeExpression is the wrong approach.
		
		$itemPatches = array();
		$resultExpressions = array();
		foreach( $patches as $itemId=>$patchData ) {
			$itemPatches[$itemId] = new EarthIT_CMIPREST_RESTAction_PatchItemAction($rc, $itemId, $patchData, $rasm);
			$resultExpressions[$itemId] = new EarthIT_CMIPREST_Expression_ActionResultExpression($itemId);
		}
		$expression = new EarthIT_CMIPREST_Expression_ArrayMergeExpression($resultExpressions);
		return self::compoundAction( $itemPatches, $expression );
	}
	
	/**
	 * @param $rasm the result assembler that will be used to post each item
	 */
	public static function multiPost(EarthIT_Schema_ResourceClass $rc, $posts, EarthIT_CMIPREST_ResultAssembler $rasm) {
		// Warning:
		// This will only work properly when:
		// - result items have IDs and
		// - the result assembler returns arrays of key => item data
		// Otherwise an ArrayMergeExpression is the wrong approach.
		
		$itemPatches = array();
		$resultExpressions = array();
		foreach( $posts as $i => $itemData ) {
			$itemPosts[$i] = new EarthIT_CMIPREST_RESTAction_PostItemAction($rc, $itemData, $rasm);
			$resultExpressions[$i] = new EarthIT_CMIPREST_Expression_ActionResultExpression($i);
		}
		$expression = new EarthIT_CMIPREST_Expression_ArrayMergeExpression($resultExpressions);
		return self::compoundAction( $itemPosts, $expression );
	}
	
	public static function compoundAction( array $subActions, EarthIT_CMIPREST_Expression $resultExpression=null ) {
		if( $resultExpression !== null ) {
			$rasm = new EarthIT_CMIPREST_ResultAssembler_ExpressionBasedCompoundResultAssembler( $resultExpression );
		} else {
			$rasm = new EarthIT_CMIPREST_ResultAssembler_NormalCompoundResultAssembler();
		}
		return new EarthIT_CMIPREST_RESTAction_CompoundAction( $subActions, $rasm );
	}
}
