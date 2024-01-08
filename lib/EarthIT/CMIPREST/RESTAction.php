<?php

/**
 * Base class for REST actions.
 */
abstract class EarthIT_CMIPREST_RESTAction implements EarthIT_CMIPREST_Action
{
	/**
	 * Returns a verb-phrase that describes what is being done
	 * which could complete a sentence like
	 * 'So and so is trying to ...' or
	 * 'You are not allowed to ...',
	 * e.g. "pick apples", "pick at the Mona Lisa"
	 */
	public abstract function getActionDescription();
	public abstract function getResultAssembler();
	
	public function jsonSerialize() : mixed {
		$props = array(
			'phpClassName' => get_class($this),
			'resourceClassName' => $this->resourceClass->getName(),
		) + get_object_vars($this);
		unset($props['resourceClass']); // Too jungley
		return $props;
	}
	
	public function __toString() {
		try {
			return EarthIT_JSON::prettyEncode($this);
		} catch( Exception $e ) {
			return get_class($this).":(error while encoding properties: ".$e->getMessage().")";
		}
	}
}
