<?php

class EarthIT_CMIPREST_UserAction_SearchAction extends EarthIT_CMIPREST_UserAction_ResourceAction
{
	protected $searchParameters;
	protected $joinBranches;
	
	public function __construct(
		$userId,
		EarthIT_Schema_ResourceClass $resourceClass,
		EarthIT_CMIPREST_SearchParameters $searchParameters,
		array $joinBranches
	) {
		parent::__construct( $userId, $resourceClass );
		$this->searchParameters = $searchParameters;
		$this->joinBranches = $joinBranches;
	}
	
	public function getSearchParameters() { return $this->searchParameters; }
	public function getJoinBranches() { return $this->joinBranches; }
	
	public function getActionDescription() {
		return "search for ".EarthIT_Schema_WordUtil::pluralize($this->getResourceClass()->getName());
	}
}
