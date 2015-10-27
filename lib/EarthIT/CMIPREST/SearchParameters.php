<?php

/**
 * Represents a search with field value constraints, ordering, and result index range.
 * It would be appropriate to subclass this in cases where additional constraints may be specified,
 * e.g. to search within a hierarchy, or by visibility to a certain user.
 *
 * TODO for v1: Replace fieldMatchers with a more general list of filters
 * 
 * @deprecated use EarthIT_Storage_Searches instead
 */
class EarthIT_CMIPREST_SearchParameters
{
	/**
	 * Array of EarthIT_CMIPREST_FieldMatcher objects,
	 * keyed by internal field name
	 *
	 * TODO: What if we want more than one constraint on a field??
	 */
	protected $fieldMatchers;
	/**
	 * List of EarthIT_CMIPREST_OrderByComponents
	 */
	protected $orderByComponents;
	/** Offset into search results at which to start including records in output */
	protected $skip;
	/** Maximum number of records to include in output */
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
