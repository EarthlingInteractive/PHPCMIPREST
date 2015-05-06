<?php

class EarthIT_CMIPREST_RequestParser_Util
{
	public static function m( $bif, $idx ) {
		return isset($bif[$idx]) && $bif[$idx] != '' ? $bif[$idx] : null;
	}
	
	public static function parseQueryString( $queryString ) {
		$params = array();
		if($queryString) parse_str($queryString, $params);
		return $params;
	}
	
	public static function parseJsonContent( Nife_Blob $content=null ) {
		if( $content === null ) return null;
		if( $content->getLength() == 0 ) return null;
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
	
	private static function findJohnByName( EarthIT_Schema $schema, EarthIT_Schema_ResourceClass $originRc, $linkName, callable $namer ) {
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
							$mungedName = $namer(EarthIT_Schema_SchemaObject::__set_state(['name'=>$refInverseName]));
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
		array $withs, callable $namer
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
		$withs, callable $namer, $pathDelimiter='.'
	) {
		if( is_scalar($withs) ) $withs = explode(',',$withs);
		if( !is_array($withs) ) throw new Exception("withs parameter must be an array or comma-delimited string.");
		$pathTree = array();
		foreach( $withs as $segment ) self::parsePathToTree(explode($pathDelimiter,$segment), $pathTree);
		return self::_withsToJohnBranches( $schema, $originRc, $pathTree, $namer );
	}
}
