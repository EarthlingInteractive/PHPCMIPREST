<?php

/**
 * @api
 */
class EarthIT_CMIPREST_RESTActionAuthorizer_DefaultRESTActionAuthorizer
implements EarthIT_CMIPREST_RESTActionAuthorizer2
{
	/** @override */
	public function preAuthorizeSimpleAction( EarthIT_CMIPREST_RESTAction $act, $ctx, array &$explanation ) {
		// TODO: Move implementation to a separate permission checker class
		$rc = $act->getResourceClass();
		$rcName = $rc->getName();
		if( $act->mayBeDestructive() ) {
			$explanation[] = "Destructive actions, such as to ".$act->getActionDescription().", are not allowed at all.";
			return false;
		}
		return EarthIT_CMIPREST_RESTActionAuthorizer::AUTHORIZED_IF_RESULTS_VISIBLE;
	}
	
	/**
	 * @overridable
	 */
	protected function itemVisible( $item, EarthIT_Schema_ResourceClass $rc, $ctx, array &$explanation ) {
		if( $rc->membersArePublic() ) {
			$explanation[] = $rc->getName()." records are public";
			return true;
		} else {
			$explanation[] = $rc->getName()." records are NOT public";
			return false;
		}
	}
	
	/** @override */
	public function itemsVisible( array $itemData, EarthIT_Schema_ResourceClass $rc, $ctx, array &$explanation ) {
		foreach( $itemData as $item ) {
			if( !$this->itemVisible($item, $rc, $ctx, $explanation) ) return false;
		}
		return true;
	}
	
	/** @override */
	public function visibleItems( array $itemData, EarthIT_Schema_ResourceClass $rc, $ctx, array &$explanation ) {
		$visibleItems = array();
		foreach( $itemData as $k=>$item ) {
			if( $this->itemVisible($item, $rc, $ctx, $explanation) ) $visibleItems[$k] = $item;
		}
		return $visibleItems;
	}
}
