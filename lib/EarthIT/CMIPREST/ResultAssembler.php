<?php

/**
 * @unstable
 */
interface EarthIT_CMIPREST_ResultAssembler
{
	public function assembleSearchResult( EarthIT_Schema_ResourceClass $rootRc, array $johnCollections, array $relevantObjects );
	public function assembleSingleResult( EarthIT_Schema_ResourceClass $rootRc, array $johnCollections, array $relevantObjects );	
	public function assemblePostResult( EarthIT_Schema_ResourceClass $rootRc, array $johnCollections, array $relevantObjects );	
	public function assemblePutResult( EarthIT_Schema_ResourceClass $rootRc, array $johnCollections, array $relevantObjects );	
}
