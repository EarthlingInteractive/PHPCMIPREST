<?php

class EarthIT_CMIPREST_UserAction_SearchAction extends EarthIT_CMIPREST_UserAction_ResourceAction
{
	protected $searchParameters;
	
	public function __construct(
		$userId,
		EarthIT_Schema_ResourceClass $resourceClass,
		EarthIT_CMIPREST_SearchParameters $searchParameters
	) {
		parent::__construct( $userId, $resourceClass );
		$this->searchParameters = $searchParameters;
	}
	
	public function getSearchParameters() { return $this->searchParameters; }
	
	public function getActionDescription() {
		return "search for ".EarthIT_Schema_WordUtil::pluralize($this->getResourceClass()->getName());
	}
}
