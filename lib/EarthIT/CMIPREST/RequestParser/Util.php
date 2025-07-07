<?php

class EarthIT_CMIPREST_RequestParser_Util
{
	public static function m( $bif, $idx, $default=null ) {
		return isset($bif[$idx]) && $bif[$idx] != '' ? $bif[$idx] : $default;
	}
	
	/**
	 * @api
	 */
	public static function parseQueryString( $queryString ) {
		$params = array();
		if($queryString) parse_str($queryString, $params);
		return $params;
	}
	
	/**
	 * @api
	 * Parses the query string into a list of [k, v] pairs.
	 * Useful when keys can have multiple values, e.g. someField=ge:123&someField=lt:456
	 */
	public static function parseQueryString2( $queryString ) {
		$parts = explode('&',$queryString);
		$re = array();
		foreach( $parts as $p ) {
			if( $p === '' ) continue;
			$kv = explode('=',$p,2);
			foreach( $kv as &$uhm ) $uhm = urldecode($uhm); unset($uhm);
			if( count($kv) == 1 ) {
				$k = $v = $kv[0];
			} else {
				list($k,$v) = $kv;
			}
			$re[] = array($k,$v);
		}
		return $re;
	}
	
	/**
	 * @api
	 * Here as a counterpart to parseQueryString, because sometimes you
	 * need to go that way.
	 */
	public static function buildQueryString( array $stuff ) {
		$p = array();
		foreach( $stuff as $k=>$v ) {
			$p[] = urlencode($k).'='.urlencode($v);
		}
		return implode('&',$p);
	}
	
	public static function parseJsonContent( Nife_Blob|EarthIT_JSON_PrettyPrintedJSONBlob|EarthIT_FileTemplateBlob|null $content=null ) {
		if( $content === null ) return null;
		if( $content->getLength() === 0 ) return null;
		if( $content instanceof Nife_Blob || $content instanceof EarthIT_JSON_PrettyPrintedJSONBlob || $content instanceof EarthIT_FileTemplateBlob ) return $content->getValue();
		$c = (string)$content;
		if( $c == '' ) return null;
		return EarthIT_JSON::decode($c);
	}
	
	protected static function getFields( EarthIT_Schema_ResourceClass $rc, array $fieldNames ) {
		$f = $rc->getFields();
		$fields = array();
		foreach( $fieldNames as $fn ) $fields[] = $f[$fn];
		return $fields;
	}
	
	/**
	 * @param string $v
	 */
	protected static function parseValue( $v, EarthIT_Schema_DataType $fieldType ) {
		switch( $fieldType->getPhpTypeName() ) {
		case 'string': return $v;
		case 'int': return (int)$v;
		case 'float': return (float)$v;
		case 'bool':
			return EarthIT_CMIPREST_Util::parseBoolean($v);
		default:
			throw new Exception("Don't know how to parse \"$v\" as a ".$fieldType->getName());
		}
	}
	
	public static function keyByMappedName( array $schemaObjects, $schemaObjectNamer ) {
		$keyed = array();
		foreach( $schemaObjects as $obj ) {
			$name = call_user_func($schemaObjectNamer,$obj);
			if( $name == '' ) throw new Exception(
				"Naming function returned empty string for name of schema object '".
				$obj->getName()."', which obviously isn't right.");
			$keyed[$name] = $obj;
		}
		return $keyed;
	}
	
	public static function parseFilter( array $requestFilters, EarthIT_Schema_ResourceClass $rc, array $fields ) {
		$filterComponents = array();
		foreach( $requestFilters as $filter ) {
			$field = $fields[$filter['fieldName']];
			$filterComponents[] = EarthIT_Storage_ItemFilters::fieldValueFilter( $filter['opName'], $filter['pattern'], $field, $rc );
		}
		return EarthIT_Storage_ItemFilters::anded($filterComponents);
	}
	
	// Better than the first one!
	public static function parseFilter2( array $requestFilters, EarthIT_Schema_ResourceClass $rc, EarthIT_Schema $schema ) {
		$filterComponents = array();
		foreach( $requestFilters as $filter ) {
			$filterComponents[] = EarthIT_Storage_ItemFilters::parsePattern(
				$filter['fieldName'], $filter['pattern'],
				$rc, $schema, true );
		}
		return EarthIT_Storage_ItemFilters::anded($filterComponents);
	}
	
	public static function parseComparator( array $requestOrderBys, EarthIT_Schema_ResourceClass $rc, array $fields ) {
		$fieldwiseComparatorComponents = array();
		foreach( $requestOrderBys as $orderBy ) {
			$field = $fields[$orderBy['fieldName']];
			$fieldwiseComparatorComponents[] = new EarthIT_Storage_FieldwiseComparatorComponent($field->getName(), $orderBy['direction']);
		}
		return new EarthIT_Storage_FieldwiseComparator($fieldwiseComparatorComponents);
	}
	
	/**
	 * a.b.c.d -> { a: { b: { c: { d: {} } } } }
	 */
	protected static function parsePathToTree( $path, array &$into ) {
		if( $path === '' ) return;
		if( is_string($path) ) $path = explode('.', $path);
		if( count($path) == 0 ) return;
		
		if( !isset($into[$path[0]]) ) {
			$into[$path[0]] = array();
		}
		
		self::parsePathToTree( array_slice($path, 1), $into[$path[0]] );
	}
	
	const JP_EXPLICIT_DIRECT_SINGULAR = 60;
	const JP_EXPLICIT_INVERSE_PLURAL = 50;
	const JP_EXPLICIT_INVERSE_SINGULAR = 40;
	const JP_EXPLICIT_INVERSE_IMPLICIT_PLURAL = 30;
	const JP_IMPLICIT_INVERSE_PLURAL = 20;
	const JP_IMPLICIT_INVERSE_SINGULAR = 10;
	
	private static function findJohnByName( EarthIT_Schema $schema, EarthIT_Schema_ResourceClass $originRc, $linkName, $namer ) {

		/**
		 * There may be multiple candidates for what Y in X;with=Y refers to.
		 * From most to least precedence:
		 * 
		 * Explicit direct reference:
		 * 60. X defines Y : reference(...); this is the easiest to find and taks precedence over everything else - singular
		 * Explicit inverse references:
		 * 50. Some class defines a reference(X) : inverse collection name @ "Y" - plural
		 * 40. Some class defines a reference(X) : inverse name @ "Y" - singular
		 * 30. Some class defines a reference(X) : inverse name @ "Z" where plural("Z") = "Y" - plural
		 * Implicit (based on class name) references:
		 * 20. Some class with plural(name) = "Y" - plural
		 * 10. Some class with name = "Y" - singular
		 *
		 * If multiple johns are found with the *same priority*, the query is ambiguous.
		 * Otherwise we use the john found with highest precedence.
		 */
		
		foreach( $originRc->getReferences() as $refName=>$ref ) {
			$name = $namer($ref);
			if( $linkName == $name ) {
				// This is the JP_EXPLICIT_DIRECT_SINGULAR case
				$targetRc = $schema->getResourceClass($ref->getTargetClassName());
				return new EarthIT_CMIPREST_John(
					$originRc, self::getFields($originRc, $ref->getOriginFieldNames()),
					$targetRc, self::getFields($targetRc, $ref->getTargetFieldNames()),
					false
				);
			}
		}
		
		/**
		 * array of precedence (see above list) => list of possible johns
		 */
		$candidates = array();
		
		foreach( $schema->getResourceClasses() as $targetRc ) {
			// Find any explicitly-named inverse references
			foreach( $targetRc->getReferences() as $inverseRef ) {
				if( $inverseRef->getTargetClassName() == $originRc->getName() ) {
					foreach( $inverseRef->getPropertyValues(EarthIT_CMIPREST_NS::INVERSE_NAME) as $inverseName ) {
						$mungedRefInverseName = $namer(EarthIT_Schema_SchemaObject::__set_state(array('name'=>$inverseName)));
						if( $mungedRefInverseName === $linkName ) {
							$candidates[self::JP_EXPLICIT_INVERSE_SINGULAR][] = new EarthIT_CMIPREST_John(
								$originRc, self::getFields($originRc, $inverseRef->getTargetFieldNames()),
								$targetRc, self::getFields($targetRc, $inverseRef->getOriginFieldNames()),
								false
							);
						}
						
						$inverseNamePlural = EarthIT_Schema_WordUtil::pluralize($inverseName);
						$mungedRefInverseName = $namer(EarthIT_Schema_SchemaObject::__set_state(array('name'=>$inverseNamePlural)));
						if( $mungedRefInverseName === $linkName ) {
							$candidates[self::JP_EXPLICIT_INVERSE_IMPLICIT_PLURAL][] = new EarthIT_CMIPREST_John(
								$originRc, self::getFields($originRc, $inverseRef->getTargetFieldNames()),
								$targetRc, self::getFields($targetRc, $inverseRef->getOriginFieldNames()),
								true
							);
						}
					}
					
					foreach( $inverseRef->getPropertyValues(EarthIT_CMIPREST_NS::INVERSE_COLLECTION_NAME) as $inverseCollectionName ) {
						$mungedRefInverseName = $namer(EarthIT_Schema_SchemaObject::__set_state(array('name'=>$inverseCollectionName)));
						if( $mungedRefInverseName === $linkName ) {
							$candidates[self::JP_EXPLICIT_INVERSE_PLURAL][] = new EarthIT_CMIPREST_John(
								$originRc, self::getFields($originRc, $inverseRef->getTargetFieldNames()),
								$targetRc, self::getFields($targetRc, $inverseRef->getOriginFieldNames()),
								true
							);
						}
					}
					
					// Find any implicit references
					foreach( array(
						self::JP_IMPLICIT_INVERSE_PLURAL => true,
						self::JP_IMPLICIT_INVERSE_SINGULAR => false
					) as $prec => $plural ) {
						$targetRcMungedName = $namer($targetRc,$plural); // This should take collection name into account
						
						if( $targetRcMungedName == $linkName ) {
							$john = new EarthIT_CMIPREST_John(
								$originRc, self::getFields($originRc, $inverseRef->getTargetFieldNames()),
								$targetRc, self::getFields($targetRc, $inverseRef->getOriginFieldNames()),
								$plural
							);
							$candidates[$prec][] = $john;
						}
					}
				}
			}
		}
		
		krsort($candidates);
		foreach( $candidates as $precedence => $johns ) {
			if( count($johns) > 1 ) {
				$list = array();
				foreach( $johns as $j ) $list[] = (string)$j;
				throw new Exception(
					"The link '$linkName' from ".$originRc->getName()." is ambiguous.\n".
					"It could indicate any of the following links: ".implode('; ',$list)
				);
			}
			foreach( $johns as $j ) return $j;
		}
		
		// Otherwise we found nothing.
		throw new Exception("Can't find '$linkName' link from ".$originRc->getName());
	}

	private static function _withsToJohnBranches(
		EarthIT_Schema $schema, EarthIT_Schema_ResourceClass $originRc,
		array $withs, $namer
	) {
		$branches = array();
		foreach( $withs as $k=>$subWiths ) {
			$john = self::findJohnByName( $schema, $originRc, $k, $namer );
			$branches[$k] = new EarthIT_CMIPREST_JohnTreeNode(
				$john,
				self::_withsToJohnBranches( $schema, $john->targetResourceClass, $subWiths, $namer )
			);
		}
		return $branches;
	}
	
	public static function withsToJohnBranches(
		EarthIT_Schema $schema, EarthIT_Schema_ResourceClass $originRc,
		$withs, $namer, $pathDelimiter='.'
	) {
		if( is_scalar($withs) ) $withs = explode(',',$withs);
		if( !is_array($withs) ) throw new Exception("withs parameter must be an array or comma-delimited string.");
		$pathTree = array();
		foreach( $withs as $segment ) self::parsePathToTree(explode($pathDelimiter,$segment), $pathTree);
		return self::_withsToJohnBranches( $schema, $originRc, $pathTree, $namer );
	}
}
