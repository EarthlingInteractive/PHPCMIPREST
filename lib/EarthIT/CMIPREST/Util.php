<?php

class EarthIT_CMIPREST_Util
{
	/**
	 * Convert a value to the named PHP scalar type using PHP's default conversion.
	 */
	public static function cast( $value, $phpType ) {
		if( $phpType === null or $value === null ) return $value;
		
		switch( $phpType ) {
		case 'string': return (string)$value;
		case 'float': return (float)$value;
		case 'int': return (int)$value;
		case 'bool': return (bool)$value;
		default:
			throw new Exception("Don't know how to cast to PHP type '$phpType'.");
		}
	}
}
