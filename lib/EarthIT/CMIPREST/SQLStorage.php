<?php

// TODO: replace this and other CMIPREST_*Storage with a single
// CMIPRESTStorageAdapter that wraps a Storage_Item*er object */

// Note that johnlySearchItems isn't going to work.
// It's full of undefined variables and stuff from before it was
// switched to take a Storage_Search instead of a
// CMIPREST_SearchParameters, etc.

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
	
	protected function dbObjectToInternal( EarthIT_Schema_ResourceClass $rc, array $obj ) {
		$fieldValues = array();
		foreach( EarthIT_CMIPREST_Util::storableFields($rc) as $f ) {
			$fieldName = $f->getName();
			$columnName = $this->dbObjectNamer->getColumnName($rc, $f);
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
			$columnValues[$this->dbObjectNamer->getColumnName($rc, $fields[$fieldName])] = $value;
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
	
	private function buildSearchSql(
		EarthIT_Storage_Search $search,
		$tableAlias,
		EarthIT_DBC_ParamsBuilder $PB
	) {
		$rc = $search->getResourceClass();
		$fields = $rc->getFields();
		$whereClauses = array();
		$tableParamName = $PB->newParam('table', $this->rcTableExpression( $rc ));
		
		$filterSql = $search->getFilter()->toSql($tableAlias, $this->dbObjectNamer, $PB );
		
		if( count($orderByComponents = $search->getComparator()->getComponents()) > 0 ) {
			$orderBySqlComponents = array();
			foreach( $orderByComponents as $oc ) {
				$columnName = $this->dbObjectNamer->getColumnName($fields[$oc->getFieldName()]);
				$orderByColumnParamName = $PU->newParam('orderBy', new EarthIT_DBC_SQLIdentifier($columnName));
				$orderBySqlComponents[] = "{{$orderByColumnParamName}} ".$oc->getDirection();
			}
			$orderBySection = "ORDER BY ".implode(', ',$orderBySqlComponents)."\n";
		} else $orderBySection = '';
		
		$limitClauseParts = array();
		if( $search->getLimit() !== null ) $limitClauseParts[] = "LIMIT ".$sp->getLimit();
		if( $search->getSkip() != 0 ) $limitClauseParts[] = "OFFSET ".$sp->getSkip();
		$limitSection = $limitClauseParts ? implode(' ',$limitClauseParts)."\n" : '';
		
		return array(
			'emptyResultSetGuaranteed' => false,
			'fromSection' => "FROM {{$tableParamName}} AS {$tableAlias}\n",
			'whereSection' => "WHERE $filterSql\n",
			'orderBySection' => $orderBySection,
			'limitSection' => $limitSection
		);
	}
	
	protected function fieldSelects( $rc, $tableAlias, array &$params ) {
		$selectedThings = array();
		foreach( EarthIT_CMIPREST_Util::storableFields($rc) as $f ) {
			$columnNameParam = EarthIT_DBC_ParameterUtil::newParamName('column');
			$fieldNameParam = EarthIT_DBC_ParameterUtil::newParamName('field');
			$params[$columnNameParam] = new EarthIT_DBC_SQLIdentifier($this->dbObjectNamer->getColumnName($rc, $f));
			$params[$fieldNameParam] = new EarthIT_DBC_SQLIdentifier($f->getName());
			$selectedThings[] = "{$tableAlias}.{{$columnNameParam}} AS {{$fieldNameParam}}";
		}
		return $selectedThings;
	}
	
	protected function evaluateJohnTree(
		EarthIT_Storage_Search $search,
		array $johns,
		array $branches,
		$path, array &$results
	) {
		foreach( $branches as $k=>$johnTreeNode ) {
			$newJohns = $johns;
			$newJohns[] = $johnTreeNode->getJohn();
			$this->evaluateJohnTree( $search, $newJohns, $johnTreeNode->branches, $path.".".$k, $results );
		}
		
		$rc = $search->getResourceClass();
		
		$results[$path] = array();
		
		$aliasNum = 0;
		$alias0 = 'a'.($aliasNum++);

		$PB = new EarthIT_DBC_ParamsBuilder();
		$searchQuery = $this->buildSearchSql( $search, 'root', $PB );
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
					$originColumnParam = $PB->newParam('originColumn',
						new EarthIT_DBC_SQLIdentifier($this->dbObjectNamer->getColumnName($originRc, $j->originLinkFields[$li])));
					$targetColumnParam = $PB->newParam('targetColumn',
						new EarthIT_DBC_SQLIdentifier($this->dbObjectNamer->getColumnName($targetRc, $j->targetLinkFields[$li])));
					$joinConditions[] = "{$targetAlias}.{{$targetColumnParam}} = {$originAlias}.{{$originColumnParam}}";
				}
				$targetTableParam = $PB->newParam('targetTable', $this->rcTableExpression($targetRc));
				$joins[] = "JOIN {{$targetTableParam}} AS {$targetAlias} ON ".implode(' AND ',$joinConditions);
				$originAlias = $targetAlias;
			}
		}
		
		// The root table needs to be wrapped
		// into its own sub-query because it can be ordered and limited.
		// We want the order and limit clauses to apply only to the root
		// table, not the things we join to!
		
		$selectedValueSqls = EarthIT_Storage_Util::formatSelectComponents(
			$this->sqlGenerator->makeDbExternalFieldValueSqls(
				$targetRc->getFields(), $targetRc, $targetAlias, $PB), $PB);
		if( count($selectedValueSqls) == 0 ) {
			throw new Exception("Can't select zero stuff.");
		}

		$sql =
			"SELECT ".implode(', ',$selectedValueSqls)."\n".
			"FROM (\n\t".str_replace("\n","\n\t",
				"SELECT *\n".
				$searchQuery['fromSection'].
				$searchQuery['whereSection'].
				$searchQuery['orderBySection'].
				$searchQuery['limitSection']
			).") AS $alias0\n".
			(count($joins) ? implode("\n",$joins)."\n" : '');
		
		foreach( $this->sqlRunner->fetchRows($sql, $PB->getParams()) AS $dbObj ) {
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
		$this->evaluateJohnTree( $search, array(), $johnBranches, 'root', $results );
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
