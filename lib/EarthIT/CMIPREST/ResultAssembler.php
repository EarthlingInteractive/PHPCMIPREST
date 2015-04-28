<?php

/**
 * @unstable
 */
interface EarthIT_CMIPREST_ResultAssembler
{
	/**
	 * Return false if this assembler does not actually need an input
	 * to generate its response.  If false, then storage actions that
	 * have no side-effects whose output is used only to generate a
	 * result to be assembled may be skipped (e.g. the retrieval after
	 * a stored item after a POST).
	 */
	public function needsResult();
	
	/**
	 * Assemble the result into whatever format.
	 */
	public function __invoke( EarthIT_CMIPREST_StorageResult $result );
}
