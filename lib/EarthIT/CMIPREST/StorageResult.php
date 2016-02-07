<?php

/**
 * @api
 */
class EarthIT_CMIPREST_StorageResult
implements EarthIT_CMIPREST_ActionResult
{
	protected $rootRc;
	protected $johnCollections;
	protected $itemCollections;
	
	// TODO: Include range info ('this is items 10-19 of 0-3999')
	
	/**
	 * @param $johnCollections array with keys of form 'root.x.y.z' and
	 *  values are lists of Johns from the root to the named
	 *  sub-object.  This should include a 'root' entry that maps to an
	 *  empty list of Johns.
	 */
	public function __construct( EarthIT_Schema_ResourceClass $rootRc, array $johnCollections, array $itemCollections ) {
		$this->rootRc = $rootRc;
		$this->johnCollections = $johnCollections;
		$this->itemCollections = $itemCollections;
	}
	
	public function getRootResourceClass() {
		return $this->rootRc;
	}
	public function getJohnCollections() {
		return $this->johnCollections;
	}
	public function getItemCollections() {
		return $this->itemCollections;
	}
}
