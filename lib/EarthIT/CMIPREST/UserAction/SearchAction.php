<?php

class EarthIT_CMIPREST_UserAction_SearchAction extends EarthIT_CMIPREST_UserAction_ResourceAction
{
	protected $searchParameters;
	protected $johnBranches;
	
	public function __construct(
		$userId,
		EarthIT_Schema_ResourceClass $resourceClass,
		EarthIT_CMIPREST_SearchParameters $searchParameters,
		array $johnBranches
	) {
		parent::__construct( $userId, $resourceClass );
		$this->searchParameters = $searchParameters;
		$this->johnBranches = $johnBranches;
	}
	
	public function getSearchParameters() { return $this->searchParameters; }
	public function getJohnBranches() { return $this->johnBranches; }
	
	public function getActionDescription() {
		return "search for ".EarthIT_Schema_WordUtil::pluralize($this->getResourceClass()->getName());
	}
}
