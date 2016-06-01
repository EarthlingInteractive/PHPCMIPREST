<?php

class EarthIT_CMIPREST_Namers
{
	protected static $standardCamelCaseNamer;
	public static function getStandardCamelCaseNamer() {
		if( self::$standardCamelCaseNamer === null ) {
			self::$standardCamelCaseNamer = new EarthIT_CMIPREST_CollectionNameMindingSchemaObjectNamer(
				new EarthIT_Schema_ConventionalSchemaObjectNamer( function($name,$plural=false) {
					if($plural) $name = EarthIT_Schema_WordUtil::pluralize($name);
					return EarthIT_Schema_WordUtil::toCamelCase($name);
				})
			);
		}
		return self::$standardCamelCaseNamer;
	}
}
