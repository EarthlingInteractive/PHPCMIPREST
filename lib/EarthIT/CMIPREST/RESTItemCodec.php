<?php

/**
 * Item codec for transforming to/from standard REST-format.
 * i.e. camelCased field names and only REST returnable/
 */ 
class EarthIT_CMIPREST_RESTItemCodec implements EarthIT_CMIPREST_ItemCodec
{
	protected static $instance;
	public static function getInstance() {
		if( self::$instance === null ) self::$instance = new self();
		return self::$instance;
	}
	
	protected $filterInputs;
	protected $filterOutputs;
	public function __construct($filterInputs=true, $filterOutputs=null) {
		if( $filterOutputs === null ) $filterOutputs = $filterInputs;
		$this->filterInputs = $filterInputs;
		$this->filterOutputs = $filterOutputs;
	}
	
	public function encodeItems( array $items, EarthIT_Schema_ResourceClass $rc ) {
		$fieldNameMap = array();
		foreach( $this->filterOutputs ? EarthIT_CMIPREST_Util::restReturnableFields($rc) : $rc->getFields() as $fn=>$f ) {
			$restName = EarthIT_Schema_WordUtil::toCamelCase($fn);
			$fieldNameMap[$restName] = $fn;
		}
		
		$restItems = array();
		foreach( $items as $k=>$item ) {
			$restItem = array();
			foreach( $fieldNameMap as $rfn=>$fn ) {
				if( array_key_exists($fn,$item) ) $restItem[$rfn] = $item[$fn];
			}
			$restItems[$k] = $restItem;
		}
		return $restItems;
	}
	public function decodeItems( array $restItems, EarthIT_Schema_ResourceClass $rc ) {
		$fieldNameMap = array();
		foreach( $this->filterInputs ? EarthIT_CMIPREST_Util::restAssignableFields($rc) : $rc->getFields() as $fn=>$f ) {
			$restName = EarthIT_Schema_WordUtil::toCamelCase($fn);
			$fieldNameMap[$fn] = $restName;
		}
		
		$items = array();
		foreach( $restItems as $k=>$restItem ) {
			$item = array();
			foreach( $fieldNameMap as $fn=>$rfn ) {
				if( array_key_exists($rfn,$restItem) ) $item[$fn] = $restItem[$rfn];
			}
			$items[$k] = $item;
		}
		return $items;
	}
}
