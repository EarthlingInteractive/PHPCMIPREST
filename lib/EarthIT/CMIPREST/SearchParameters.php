<?php

/**
 * Represents a search with field value constraints, ordering, and result index range.
 * It would be appropriate to subclass this in cases where additional constraints may be specified,
 * e.g. to search within a hierarchy, or by visibility to a certain user.
 */
class EarthIT_CMIPREST_SearchParameters
{
	protected $fieldMatchers;
	protected $ordering;
	protected $skip;
	protected $limit;
	
	public function __construct( array $fieldMatchers, array $ordering, $skip, $limit ) {
		$this->fieldMatchers = $fieldMatchers;
		$this->ordering = $ordering;
		$this->skip = $skip;
		$this->limit = $limit;
	}
	
	public function getFieldMatchers() { return $this->fieldMatcher; }
	public function getOrdering() { return $this->ordering; }
	public function getSkip() { return $this->skip; }
	public function getLimit() { return $this->limit; }
}
