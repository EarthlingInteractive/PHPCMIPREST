<?php

use EarthIT_CMIPREST_ResultAssembler_NOJResultAssembler AS NOJRA;

class EarthIT_CMIPREST_RequestParser_CMIPResultAssemblerFactory
implements EarthIT_CMIPREST_RequestParser_ResultAssemblerFactory
{
	public static function getInstance() {
		// This could return a cached instance but it's easier to just new one up
		return new self(true);
	}
	
	protected $defaultOptions;
	/**
	 * @param $defaultOptions array of defaultOptions to be passed to NOJResultAssembler OR true|false to indicate just the 'keyItemsById' option
	 *   (see NOJResultAssembler constructor)
	 */
	public function __construct( $defaultOptions=array() ) {
		if( is_bool($defaultOptions) === true ) {
			$defaultOptions = array(NOJRA::KEY_BY_IDS => $defaultOptions);
		}
		if( !is_array($defaultOptions) ) {
			throw new Exception(
				"\$defaultOptions parameter to CMIPResultAssemblerFactory must be an array.\n".
				"Got ".var_export($defaultOptions,true));
		}
		$this->defaultOptions = $defaultOptions;
	}
	
	protected static function meth($actionClass) {
		switch($actionClass) {
		case self::AC_GET: case self::AC_POST: case self::AC_PUT: case self::AC_PATCH:
			return 'assembleSingleItemResult';
		case self::AC_SEARCH: case self::AC_MULTIPOST: case self::AC_MULTIPATCH;
			return 'assembleMultiItemResult';
		case self::AC_DELETE:
			return 'assembleDeleteResult';
		default:
			throw new Exception("Unrecognized action class '$actionClass'");
		}
	}
	
	public function getResultAssembler( $actionClass, array $options=array() ) {
		return new EarthIT_CMIPREST_ResultAssembler_NOJResultAssembler(
			self::meth($actionClass),
			$options + $this->defaultOptions);
	}
}
