<?php

/**
 * @api
 * @unstable
 * TODO: Add content-type parameter to parse
 */
interface EarthIT_CMIPREST_RequestParser
{
	/**
	 * @api
	 * @param string $requestMethod HTTP request method; GET, PUT, POST, etc
	 * @param string $path Post-"/api" path.  e.g. for /api;foop/123 this would be ";foop/123"
	 * @param string $queryString the thing after "?"
	 * @param Blob|null $content request content
	 * @return null|array Array representing the parsed request, null if it was unparseable
	 */
	public function parse( $requestMethod, $path, $queryString, Nife_Blob $content=null );
	
	/**
	 * @api
	 * @param array $request Request data as returned by parse
	 * @return EarthIT_CMIPREST_Action
	 */
	public function toAction( array $request );
}