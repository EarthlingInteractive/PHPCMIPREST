<?php

/**
 * @api
 */
class EarthIT_CMIPREST_RESTActionAuthorizer_DefaultRESTActionAuthorizer
implements EarthIT_CMIPREST_RESTActionAuthorizer
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
	
	/** @override */
	public function itemsVisible( array $itemData, EarthIT_Schema_ResourceClass $rc, $ctx, array &$explanation ) {
		if( $rc->membersArePublic() ) {
			$explanation[] = $rc->getName()." records are public";
			return true;
		} else {
			$explanation[] = $rc->getName()." records are NOT public";
			return false;
		}
	}
}
