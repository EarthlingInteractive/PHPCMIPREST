<?php

// TODO: replace this and other CMIPREST_*Storage with a single
// CMIPRESTStorageAdapter that wraps a Storage_Item*er object */
class EarthIT_CMIPREST_SQLStorage
extends EarthIT_Storage_SQLStorage
implements EarthIT_CMIPREST_Storage
{
	protected $dbNamespacePath = array();
	public function setDbNamespacePath( $path ) {
		if( is_scalar($path) ) $path = explode('.', $path);
		if( !is_array($path) ) {
			throw new Exception("DB namespace path must be an array of strings or a single period.delimited.string.");
		}
		$this->dbNamespacePath = $path;
	}
	
	/**
	 * Return an EarthIT_DBC_SQLExpression that identifies the table.
	 * If a dbNamespacePath has been configured, it will be part of the expression.
	 *
	 * @deprecated
	 */
	protected function rcTableExpression( EarthIT_Schema_ResourceClass $rc ) {
		$components = array();
		foreach( $this->dbNamespacePath as $ns ) {
			$components[] = new EarthIT_DBC_SQLIdentifier($ns);
		}
		foreach( $rc->getDbNamespacePath() as $ns ) {
			$components[] = new EarthIT_DBC_SQLIdentifier($ns);
		}
		// TODO for breaking release: Remove getTableNameOverride; the namer should do that.
		$components[] = new EarthIT_DBC_SQLIdentifier($rc->getTableNameOverride() ?: $this->dbObjectNamer->getTableName($rc));
		return new EarthIT_DBC_SQLNamespacePath($components);
	}
	
	/** i.e. 'name of column corresponding to field' */
	protected function fieldDbName( EarthIT_Schema_ResourceClass $rc, EarthIT_Schema_Field $f ) {
		// TODO for breaking release: Remove getTableNameOverride; the namer should do that.
		return $f->getColumnNameOverride() ?: $this->dbObjectNamer->getColumnName( $rc, $f );
	}
	
	//// Conversion
	
	protected static function valuesOfTypeShouldBeSelectedAsJson( EarthIT_Schema_DataType $t ) {
		return $t->getSqlTypeName() == 'JSON' and $t->getPhpTypeName() == 'JSON value';
	}
	
	protected static function valuesOfTypeShouldBeSelectedAsGeoJson( EarthIT_Schema_DataType $t ) {
		return
			preg_match('/^(GEOMETRY|GEOGRAPHY)(\(|$)/', $t->getSqlTypeName()) &&
			$t->getPhpTypeName() == 'GeoJSON array';
	}
	
	protected static function dbToPhpValue( EarthIT_Schema_DataType $t, $value ) {
		if( self::valuesOfTypeShouldBeSelectedAsGeoJson($t) || self::valuesOfTypeShouldBeSelectedAsJson($t) ) {
			return $value === null ? null : EarthIT_JSON::decode($value);
		}
		// Various special rules may end up here
		return EarthIT_CMIPREST_Util::cast( $value, $t->getPhpTypeName() );
	}
	
	protected function internalObjectToDb( EarthIT_Schema_ResourceClass $rc, array $obj, array &$params ) {
		$columnNamer = $this->dbObjectNamer;
		$columnValues = array();
		foreach( EarthIT_CMIPREST_Util::storableFields($rc) as $f ) {
			$fieldName = $f->getName();
			if( array_key_exists($fieldName, $obj) ) {
				$value = $obj[$fieldName];
				
				if( self::valuesOfTypeShouldBeSelectedAsGeoJson($f->getType()) and $value !== null ) {
					$paramName = EarthIT_DBC_ParameterUtil::newParamName('geojson');
					$params[$paramName] = json_encode($value);
					$dbValue = new EarthIT_DBC_BaseSQLExpression("ST_GeomFromGeoJSON({{$paramName}})");
				} else if( self::valuesOfTypeShouldBeSelectedAsJson($f->getType()) and $value !== null ) {
					$dbValue = EarthIT_JSON::prettyEncode($value);
				} else {
					$dbValue = $value;
				}
				$columnValues[$this->fieldDbName($rc, $f)] = $dbValue;
			}
		}
		return $columnValues;
	}
	
	protected function dbObjectToInternal( EarthIT_Schema_ResourceClass $rc, array $obj ) {
		$fieldValues = array();
		foreach( EarthIT_CMIPREST_Util::storableFields($rc) as $f ) {
			$fieldName = $f->getName();
			$columnName = $this->fieldDbName($rc, $f);
			$fieldValues[$f->getName()] = self::dbToPhpValue($f->getType(), $obj[$columnName]);
		}
		return $fieldValues;
	}
	
	//// SQL generation
	
	protected function itemIdToColumnValues( EarthIT_Schema_ResourceClass $rc, $id ) {
		$fieldValues = EarthIT_CMIPREST_Util::idToFieldValues( $rc, $id );
		$columnValues = array();
		$fields = $rc->getFields();
		foreach( $fieldValues as $fieldName=>$value ) {
			$columnValues[$this->fieldDbName($rc, $fields[$fieldName])] = $value;
		}
		return $columnValues;
	}
	
	/**
	 * @param array $columnValues array of column name => column value
	 * @param array &$params column and value parameter values will be put here
	 * @return array of "{col...X} = {val...X}" strings, one per column,
	 *   with {placeholders} corresponding to the keys added to $params
	 */
	protected static function encodeColumnValuePairs( array $columnValues, array &$params ) {
		$parts = array();
		foreach( $columnValues as $colName=>$val ) {
			$cnp = EarthIT_DBC_ParameterUtil::newParamName('col');
			$cvp = EarthIT_DBC_ParameterUtil::newParamName('val');
			$params[$cnp] = new EarthIT_DBC_SQLIdentifier($colName);
			$params[$cvp] = $val;
			$parts[] = "{{$cnp}} = {{$cvp}}";
		}
		return $parts;
	}
	
	//// Build complimicated queries
	
	protected function buildSelects( EarthIT_Schema_ResourceClass $rc, array &$params, $tableAlias=null ) {
		$taPrefix = $tableAlias ? "{$tableAlias}." : '';
		$selects = array();
		foreach( EarthIT_CMIPREST_Util::storableFields($rc) as $f ) {
			$columnName = $this->fieldDbName($rc, $f);
			$columnNameParam = EarthIT_DBC_ParameterUtil::newParamName('c');
			$params[$columnNameParam] = new EarthIT_DBC_SQLIdentifier($columnName);
			if( self::valuesOfTypeShouldBeSelectedAsGeoJson($f->getType()) ) {
				$selects[] = "ST_AsGeoJSON({$taPrefix}{{$columnNameParam}}) AS {{$columnNameParam}}";
			} else {
				$selects[] = "{$taPrefix}{{$columnNameParam}}";
			}
		}
		return $selects;
	}
	
	private function buildSearchSql(
		EarthIT_Schema_ResourceClass $rc,
		EarthIT_CMIPREST_SearchParameters $sp,
		$tableAlias,
		array &$params
	) {
		$fields = $rc->getFields();
		$whereClauses = array();
		$tableExpression = $this->rcTableExpression( $rc );
		$params['table'] = $tableExpression;
		foreach( $sp->getFieldMatchers() as $fieldName => $matcher ) {
			$field = $fields[$fieldName];
			$columnName = $this->fieldDbName($rc, $field);
			$columnParamName = EarthIT_DBC_ParameterUtil::newParamName('column');
			$params[$columnParamName] = new EarthIT_DBC_SQLIdentifier($columnName);
			$columnExpression = "{$tableAlias}.{{$columnParamName}}";
			$matcherSql = $matcher->toSql( $columnExpression, $fields[$fieldName]->getType()->getPhpTypeName(), $params );
			if( $matcherSql === 'TRUE' ) {
				continue;
			} else if( $matcherSql === 'FALSE' ) {
				return array('emptyResultSetGuaranteed' => true);
			}
			$whereClauses[] = $matcherSql;
		}
		
		if( count($orderByComponents = $sp->getOrderByComponents()) > 0 ) {
			$orderBySqlComponents = array();
			foreach( $orderByComponents as $oc ) {
				$orderByColumnParamName = EarthIT_DBC_ParameterUtil::newParamName('orderBy');
				$params[$orderByColumnParamName] = new EarthIT_DBC_SQLIdentifier($this->fieldDbName($rc, $oc->getField()));
				$orderBySqlComponents[] = "{{$orderByColumnParamName}}".($oc->isAscending() ? " ASC" : " DESC");
			}
			$orderBySection = "ORDER BY ".implode(', ',$orderBySqlComponents)."\n";
		} else $orderBySection = '';

		$limitClauseParts = array();
		if( $sp->getLimit() !== null ) $limitClauseParts[] = "LIMIT ".$sp->getLimit();
		if( $sp->getSkip() != 0 ) $limitClauseParts[] = "OFFSET ".$sp->getSkip();
		$limitSection = $limitClauseParts ? implode(' ',$limitClauseParts)."\n" : '';
		
		return array(
			'emptyResultSetGuaranteed' => false,
			'fromSection' => "FROM {table} AS {$tableAlias}\n",
			'whereSection' => $whereClauses ? "WHERE ".implode("\n  AND ",$whereClauses)."\n" : '',
			'orderBySection' => $orderBySection,
			'limitSection' => $limitSection
		);
	}
	
	protected function fieldSelects( $rc, $tableAlias, array &$params ) {
		$selectedThings = array();
		foreach( EarthIT_CMIPREST_Util::storableFields($rc) as $f ) {
			$columnNameParam = EarthIT_DBC_ParameterUtil::newParamName('column');
			$fieldNameParam = EarthIT_DBC_ParameterUtil::newParamName('field');
			$params[$columnNameParam] = new EarthIT_DBC_SQLIdentifier($this->fieldDbName($rc, $f));
			$params[$fieldNameParam] = new EarthIT_DBC_SQLIdentifier($f->getName());
			$selectedThings[] = "{$tableAlias}.{{$columnNameParam}} AS {{$fieldNameParam}}";
		}
		return $selectedThings;
	}
	
	protected function evaluateJohnTree(
		EarthIT_Schema_ResourceClass $rc,
		EarthIT_CMIPREST_SearchParameters $sp,
		array $johns,
		array $branches,
		$path, array &$results
	) {
		foreach( $branches as $k=>$johnTreeNode ) {
			$newJohns = $johns;
			$newJohns[] = $johnTreeNode->getJohn();
			$this->evaluateJohnTree( $rc, $sp, $newJohns, $johnTreeNode->branches, $path.".".$k, $results );
		}
		
		$results[$path] = array();
		
		$aliasNum = 0;
		$alias0 = 'a'.($aliasNum++);

		$params = array();
		$searchQuery = $this->buildSearchSql( $rc, $sp, 'root', $params );
		if( $searchQuery['emptyResultSetGuaranteed'] ) return;
		
		$joins = array();
		if( count($johns) == 0 ) {
			$targetRc = $rc;
			$targetAlias = $alias0;
		} else {
			$originAlias = $alias0;
			foreach( $johns as $j ) {
				$originRc = $j->originResourceClass;
				$targetRc = $j->targetResourceClass;
				$targetAlias = 'a'.($aliasNum++);
				$joinConditions = array();
				for( $li=0; $li<count($j->originLinkFields); ++$li ) {
					$originColumnParam = EarthIT_DBC_ParameterUtil::newParamName('originColumn');
					$targetColumnParam = EarthIT_DBC_ParameterUtil::newParamName('targetColumn');
					$params[$originColumnParam] = new EarthIT_DBC_SQLIdentifier($this->fieldDbName($originRc, $j->originLinkFields[$li]));
					$params[$targetColumnParam] = new EarthIT_DBC_SQLIdentifier($this->fieldDbName($targetRc, $j->targetLinkFields[$li]));
					$joinConditions[] = "{$targetAlias}.{{$targetColumnParam}} = {$originAlias}.{{$originColumnParam}}";
				}
				$targetTableParam = EarthIT_DBC_ParameterUtil::newParamName('targetTable');
				$params[$targetTableParam] = $this->rcTableExpression($targetRc);
				$joins[] = "JOIN {{$targetTableParam}} AS {$targetAlias} ON ".implode(' AND ',$joinConditions);
				$originAlias = $targetAlias;
			}
		}
		
		// The root table needs to be wrapped
		// into its own sub-query because it can be ordered and limited.
		// We want the order and limit clauses to apply only to the root
		// table, not the things we join to!
		
		$sql =
			"SELECT ".implode(', ', $this->buildSelects($targetRc, $params, $targetAlias))."\n".
			"FROM (\n\t".str_replace("\n","\n\t",
				"SELECT *\n".
				$searchQuery['fromSection'].
				$searchQuery['whereSection'].
				$searchQuery['orderBySection'].
				$searchQuery['limitSection']
			).") AS $alias0\n".
			(count($joins) ? implode("\n",$joins)."\n" : '');
		
		foreach( $this->sqlRunner->fetchRows($sql, $params) AS $dbObj ) {
			$results[$path][] = $this->dbObjectToInternal($targetRc, $dbObj);
		}
	}
	
	////
	
	/** @override */
	public function johnlySearchItems(
		EarthIT_Storage_Search $search,
		array $johnBranches,
		array $options=array()
	) {
		$results = array();
		$this->evaluateJohnTree( $rc, $sp, array(), $branches, 'root', $results );
		return $results;
	}
	
	/** @override */
	public function postItem( EarthIT_Schema_ResourceClass $rc, array $itemData ) {
		return EarthIT_CMIPREST_Util::postItem( $this, $rc, $itemData );
	}
	
	/** @override */
	public function putItem( EarthIT_Schema_ResourceClass $rc, $itemId, array $itemData ) {
		return EarthIT_CMIPREST_Util::putItem( $this, $rc, $itemId, $itemData );
	}
	
	/** @override */
	public function patchItem( EarthIT_Schema_ResourceClass $rc, $itemId, array $itemData ) {
		return EarthIT_CMIPREST_Util::patchItem( $this, $rc, $itemId, $itemData );
	}
	
	public function deleteItem( EarthIT_Schema_ResourceClass $rc, $itemId ) {
		$this->deleteItems($rc, EarthIT_Storage_ItemFilters::byId($itemId, $rc));
	}
}
