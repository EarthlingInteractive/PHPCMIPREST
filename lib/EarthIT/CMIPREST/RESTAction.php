<?php

/**
 * Base class for REST actions.
 */
abstract class EarthIT_CMIPREST_RESTAction implements TOGoS_Action
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
}
