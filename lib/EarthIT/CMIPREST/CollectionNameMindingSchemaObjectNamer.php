<?php

class EarthIT_CMIPREST_CollectionNameMindingSchemaObjectNamer
extends EarthIT_Schema_SimpleAbstractSchemaObjectNamer
{
	protected $next;
	public function __construct( EarthIT_Schema_SchemaObjectNamer $next ) {
		$this->next = $next;
	}
	
	public function formatName( $name, $plural=false, EarthIT_Schema $s=null ) {
		$this->next->formatName( $name, $plural, $s );
	}
	
	public function name( EarthIT_Schema_SchemaObject $obj, $plural=false, EarthIT_Schema $s=null ) {
		if( $plural and ($pluralName = $obj->getFirstPropertyValue(EarthIT_CMIPREST_NS::COLLECTION_NAME)) !== null ) {
			return $this->next->formatName($pluralName, false, $s);
		} else {
			return $this->next->name($obj, $plural, $s);
		}
	}
}
