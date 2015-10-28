<?php

class EarthIT_CMIPREST_RESTAction_SearchAction extends EarthIT_CMIPREST_RESTAction_ResourceAction
{
	protected $search;
	protected $johnBranches;
	protected $searchOptions;
	
	public function __construct(
		EarthIT_Storage_Search $search,
		array $johnBranches,
		array $searchOptions,
		EarthIT_CMIPREST_ResultAssembler $rasm
	) {
		parent::__construct( $search->getResourceClass(), $rasm );
		$this->search = $search;
		$this->johnBranches = $johnBranches;
		$this->searchOptions = $searchOptions;
	}
	
	public function getSearch() { return $this->search; }
	public function getJohnBranches() { return $this->johnBranches; }
	public function getSearchOptions() { return $this->searchOptions; }
	
	public function getActionDescription() {
		return "search for ".EarthIT_Schema_WordUtil::pluralize($this->getResourceClass()->getName());
	}

	public function mayBeDestructive() { return false; }
}
