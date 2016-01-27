<?php

interface EarthIT_CMIPREST_RequestParser_ResultAssemblerFactory
{
	const AC_GET        = 'get';
	const AC_SEARCH     = 'search';
	const AC_POST       = 'post';
	const AC_MULTIPOST  = 'multipost';
	const AC_PUT        = 'put';
	const AC_PATCH      = 'patch';
	const AC_MULTIPATCH = 'multipatch';
	const AC_DELETE     = 'delete';
	
	/**
	 * @param string $actionClass one of the AC_* constants defined above
	 *   indicating the general class of action being performed
	 */
	public function getResultAssembler( $actionClass );
}
