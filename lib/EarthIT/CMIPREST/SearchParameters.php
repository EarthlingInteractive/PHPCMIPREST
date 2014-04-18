<?php

/**
 * Represents a search with field value constraints, ordering, and result index range.
 * It would be appropriate to subclass this in cases where additional constraints may be specified,
 * e.g. to search within a hierarchy, or by visibility to a certain user.
 */
class EarthIT_CMIPREST_SearchParameters
{
	protected $fieldMatchers;
	protected $orderByComponents;
	protected $skip;
	protected $limit;
	
	public function __construct( array $fieldMatchers, array $orderByComponents, $skip, $limit ) {
		$this->fieldMatchers = $fieldMatchers;
		$this->orderByComponents = $orderByComponents;
		$this->skip = $skip;
		$this->limit = $limit;
	}
	
	public function getFieldMatchers() { return $this->fieldMatchers; }
	public function getOrderByComponents() { return $this->orderByComponents; }
	public function getSkip() { return $this->skip; }
	public function getLimit() { return $this->limit; }
}
