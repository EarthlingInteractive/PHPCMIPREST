<?php

class EarthIT_CMIPREST_RESTAction_SearchAction extends EarthIT_CMIPREST_RESTAction_ResourceAction
{
	protected $searchParameters;
	protected $johnBranches;
	
	public function __construct(
		EarthIT_Schema_ResourceClass $resourceClass,
		EarthIT_CMIPREST_SearchParameters $searchParameters,
		array $johnBranches,
		EarthIT_CMIPREST_ResultAssembler $rasm
	) {
		parent::__construct( $resourceClass, $rasm );
		$this->searchParameters = $searchParameters;
		$this->johnBranches = $johnBranches;
	}
	
	public function getSearchParameters() { return $this->searchParameters; }
	public function getJohnBranches() { return $this->johnBranches; }
	
	public function getActionDescription() {
		return "search for ".EarthIT_Schema_WordUtil::pluralize($this->getResourceClass()->getName());
	}

	public function mayBeDestructive() { return false; }
}
