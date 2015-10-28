<?php

class EarthIT_CMIPREST_RequestParser_Util
{
	public static function m( $bif, $idx ) {
		return isset($bif[$idx]) && $bif[$idx] != '' ? $bif[$idx] : null;
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
	
	public static function parseJsonContent( Nife_Blob $content=null ) {
		if( $content === null ) return null;
		if( $content->getLength() === 0 ) return null;
		if( $content instanceof EarthIT_JSON_PrettyPrintedJSONBlob ) return $content->getValue();
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
			switch( $v ) {
			case '1': case 'true' : return true;
			case '0': case 'false': return false;
			default:
				throw new Exception("Don't know how to parse \"$v\" as a boolean value (try using 'true', 'false', '1', or '0').");
			}
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
	
	/**
	 * @param array $specs array of array('fieldName'=>field name (external form), 'direction'=>'ASC'|'DESC')
	 */
	public static function orderByComponents( array $specs, EarthIT_Schema_ResourceClass $rc, $fieldNamer ) {
		$fieldsByName = $rc->getFields();
		$fieldsByExternalName = array();
		foreach( $fieldsByName as $f ) {
			$fieldsByExternalName[call_user_func($fieldNamer,$f)] = $f;
		}
		
		$orderByComponents = array();
		foreach( $specs as $spec ) {
			if( isset($fieldsByExternalName[$spec['fieldName']]) ) {
				$field = $fieldsByExternalName[$spec['fieldName']];
			} else {
				throw new Exception("Unknown field in orderBy: '{$spec['fieldName']}'");
			}
			$orderByComponents[] = new EarthIT_CMIPREST_OrderByComponent($field, $spec['direction']==='ASC');
		}
		return $orderByComponents;
	}
		
	public static function parseOrderByComponents( $v, EarthIT_Schema_ResourceClass $rc, $fieldNamer ) {
		if( is_string($v) ) $v = explode(',',$v);
		
		$specs = array();
		
		foreach( $v as $cv ) {
			if( $cv[0] == '+' ) {
				$ascending = true;
				$cv = substr($cv,1);
			} else if( $cv[0] == '-' ) {
				$ascending = false;
				$cv = substr($cv,1);
			} else $ascending = true;
			
			$specs[] = array('fieldName'=>$cv, 'direction'=>$ascending?'ASC':'DESC');
		}
		
		return self::orderByComponents($specs, $rc, $fieldNamer);
	}
	
	private static function findJohnByName( EarthIT_Schema $schema, EarthIT_Schema_ResourceClass $originRc, $linkName, $namer ) {
		foreach( $originRc->getReferences() as $refName=>$ref ) {
			$name = $namer($ref);
			if( $linkName == $name ) {
				$targetRc = $schema->getResourceClass($ref->getTargetClassName());
				return new EarthIT_CMIPREST_John(
					$originRc, self::getFields($originRc, $ref->getOriginFieldNames()),
					$targetRc, self::getFields($targetRc, $ref->getTargetFieldNames()),
					false
				);
			}
		}
		
		/* TODO:
		 * Eventually we should be able to define inverse relationship
		 * names and plurality in the schema, possibly falling back on
		 * the method of finding them following this comment.
		 */
		
		/*
		 * Try to find a reference from a class 'X' where plural(X) = the requested link,
		 * and return a plural John of the inverse of that reference.
		 */
		$inverseJohns = array();
		foreach( $schema->getResourceClasses() as $targetRc ) {
			foreach( array(true,false) as $plural ) {
				$targetRcMungedName = $namer($targetRc,$plural);
				foreach( $targetRc->getReferences() as $inverseRef ) {
					if( $inverseRef->getTargetClassName() == $originRc->getName() ) {
						
						// Find any explicitly-given name for this inverse link
						$refInverseName = $inverseRef->getFirstPropertyValue(EarthIT_CMIPREST_NS::INVERSE_NAME);
						if( $plural ) {
							$refPluralInverseName = $inverseRef->getFirstPropertyValue(EarthIT_CMIPREST_NS::INVERSE_COLLECTION_NAME);
							if( $refPluralInverseName === null and $refInverseName !== null ) {
								$refPluralInverseName = EarthIT_Schema_WordUtil::pluralize($refInverseName);
							}
							$refInverseName = $refPluralInverseName;
						}
						
						if( $refInverseName !== null ) {
							$mungedName = $namer(EarthIT_Schema_SchemaObject::__set_state(array('name'=>$refInverseName)));
						} else {
							// Default to the class name
							$mungedName = $targetRcMungedName;
						}
						
						if( $mungedName == $linkName ) {
							$john = new EarthIT_CMIPREST_John(
								$originRc, self::getFields($originRc, $inverseRef->getTargetFieldNames()),
								$targetRc, self::getFields($targetRc, $inverseRef->getOriginFieldNames()),
								$plural
							);
							$inverseJohns[] = $john;
						}
					}
				}
			}
		}
		if( count($inverseJohns) == 1 ) {
			return $inverseJohns[0];
		} else if( count($inverseJohns) > 1 ) {
			$list = array();
			foreach( $inverseJohns as $ij ) {
				$list[] = (string)$ij;
			}
			// Alternatively, we could just include all of them.
			throw new Exception(
				"The link '$linkName' from ".$originRc->getName()." is ambiguous.\n".
				"It could indicate any of the following links: ".implode('; ',$list)
			);
		}
		
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
