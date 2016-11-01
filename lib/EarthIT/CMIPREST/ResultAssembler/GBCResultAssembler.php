<?php

use EarthIT_CMIPREST_ResultAssembler_NOJResultAssembler as NOJRA;

/**
 * GBC = 'grouped by class'
 */
class EarthIT_CMIPREST_ResultAssembler_GBCResultAssembler extends EarthIT_CMIPREST_ResultAssembler_NOJResultAssembler
{
	public function __construct($options=array()) {
		parent::__construct('assembleMultiItemResult', $options);
	}

	protected static function hasPk($rc) {
		$indexes = $rc->getIndexes();
		if( !isset($indexes['primary']) ) return false;
		$pk = $indexes['primary'];
		if( $pk === null or count($pk->getFieldNames()) == 0 ) return false;
		return true;
	}

	protected function assembleMultiItemResult( EarthIT_Schema_ResourceClass $rootRc, array $johnCollections, array $relevantObjects ) {
		$relevantRestObjects = array();
		foreach( $johnCollections as $path => $johns ) {
			// Figure out what resource class of items we got, here
			$targetRc = count($johns) == 0 ? $rootRc : $johns[count($johns)-1]->targetResourceClass;
			$rcCollectionName = EarthIT_CMIPREST_Util::collectionName($targetRc);
			$q45d = $this->_q45( $targetRc, $relevantObjects[$path] );
			if( $this->keyByIds and self::hasPk($targetRc) ) {
				foreach( $q45d as $k=>$item ) $relevantRestObjects[$rcCollectionName][$k] = $item;
			} else {
				foreach( $q45d as $item ) $relevantRestObjects[$rcCollectionName][] = $item;
			}
		}
		
		return $relevantRestObjects;		
	}
}
