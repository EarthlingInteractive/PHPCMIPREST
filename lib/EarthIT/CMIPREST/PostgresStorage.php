<?php

class EarthIT_CMIPREST_PostgresStorage implements EarthIT_CMIPREST_Storage
{
	protected $dbAdapter;
	protected $dbNamer;
	
	public function __construct($dbAdapter, $dbNamer) {
		$this->dbAdapter = $dbAdapter;
		$this->dbNamer = $dbNamer;
	}
	
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
	 */
	protected function rcTableExpression( EarthIT_Schema_ResourceClass $rc ) {
		$components = array();
		foreach( $this->dbNamespacePath as $ns ) {
			$components[] = new EarthIT_DBC_SQLIdentifier($ns);
		}
		foreach( $rc->getDbNamespacePath() as $ns ) {
			$components[] = new EarthIT_DBC_SQLIdentifier($ns);
		}
		$components[] = new EarthIT_DBC_SQLIdentifier($rc->getTableNameOverride() ?: $this->dbNamer->getTableName($rc));
		return new EarthIT_DBC_SQLNamespacePath($components);
	}
	
	/** i.e. 'name of column corresponding to field' */
	protected function fieldDbName( EarthIT_Schema_ResourceClass $rc, EarthIT_Schema_Field $f ) {
		return $f->getColumnNameOverride() ?: $this->dbNamer->getColumnName( $rc, $f );
	}
	
	//// Conversion
	
	protected static function dbToPhpValue( $value, $phpType ) {
		// May want to do something different than just use cast
		// e.g. in case we want to interpret "010" as ten instead of eight.
		return EarthIT_CMIPREST_Util::cast( $value, $phpType );
	}
	
	protected function internalObjectToDb( EarthIT_Schema_ResourceClass $rc, array $obj ) {
		$columnNamer = $this->dbNamer;
		$columnValues = array();
		foreach( $rc->getFields() as $f ) {
			$fieldName = $f->getName();
			if( isset($obj[$fieldName]) ) {
				$columnValues[$this->fieldDbName($rc, $f)] = $obj[$fieldName];
			}
		}
		return $columnValues;
	}
	
	protected function dbObjectToInternal( EarthIT_Schema_ResourceClass $rc, array $obj ) {
		$fieldValues = array();
		foreach( $rc->getFields() as $f ) {
			$fieldName = $f->getName();
			$columnName = $this->fieldDbName($rc, $f);
			$fieldValues[$f->getName()] = self::dbToPhpValue($obj[$columnName], $f->getType()->getPhpTypeName());
		}
		return $fieldValues;
	}
	
	//// Do stuff
	
	protected function fetchRows( $sql, array $params ) {
		if( $sql == 'SELECT NOTHING' ) return array();
		$builder = new EarthIT_DBC_DoctrineStatementBuilder($this->dbAdapter);
		$stmt = $builder->makeStatement($sql, $params);
		$stmt->execute();
		return $stmt->fetchAll();
	}
	
	/** Same as fetchRows; just doesn't return anything. */
	protected function doQuery( $sql, array $params ) {
		$this->fetchRows($sql, $params);
	}
	
	//// Build complimicated queries
	
	protected function buildSearchSql(
		EarthIT_Schema_ResourceClass $rc,
		EarthIT_CMIPREST_SearchParameters $sp,
		array &$params
	) {
		$fields = $rc->getFields();
		$whereClauses = array();
		$tableAlias = 'tab';
		$tableExpression = $this->rcTableExpression( $rc );
		$params['table'] = $tableExpression;
		$sql = "SELECT * FROM {table} AS {$tableAlias}";
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
				// There will be no results!
				return 'SELECT NOTHING';
			}
			$whereClauses[] = $matcherSql;
		}
		if( $whereClauses ) $sql .= "\nWHERE ".implode("\n  AND ",$whereClauses);
		if( count($orderByComponents = $sp->getOrderByComponents()) > 0 ) {
			$orderBySqlComponents = array();
			foreach( $orderByComponents as $oc ) {
				$orderBySqlComponents[] = $this->fieldDbName($rc, $oc->getField()).($oc->isAscending() ? " ASC" : " DESC");
			}
			$sql .= "\nORDER BY ".implode(', ',$orderBySqlComponents);
		}
		$limitClauseParts = array();
		if( $sp->getLimit() !== null ) $limitClauseParts[] = "LIMIT ".$sp->getLimit();
		if( $sp->getSkip() != 0 ) $limitClauseParts[] = "OFFSET ".$sp->getSkip();
		if( $limitClauseParts ) $sql .= "\n".implode(' ',$limitClauseParts);
		
		return $sql;
	}
	
	protected function fieldSelects( $rc, $tableAlias, array &$params ) {
		$selectedThings = array();
		foreach( $rc->getFields() as $f ) {
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
		$params = array();
		$rootSql = $this->buildSearchSql( $rc, $sp, $params );
		if( $rootSql == 'SELECT NOTHING' ) $results[$path] = array();
		
		$aliasNum = 0;
		$rootAlias = 'a'.($aliasNum++);
		$joins = array();
		if( count($johns) == 0 ) {
			$targetRc = $rc;
			$targetAlias = $rootAlias;
		} else {
			$originAlias = $rootAlias;
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
		$sql = "SELECT {$targetAlias}.* FROM (\n".
			"\t".str_replace("\n","\n\t",trim($rootSql))."\n".
			") AS {$rootAlias}";
		
		if( count($joins) > 0 ) {
			$sql .= "\n".implode("\n",$joins);
		}
		
		foreach( $this->fetchRows($sql, $params) AS $dbObj ) {
			$results[$path][] = $this->dbObjectToInternal($targetRc, $dbObj);
		}
		
		foreach( $branches as $k=>$johnTreeNode ) {
			$newJohns = $johns;
			$newJohns[] = $johnTreeNode->getJohn();
			$this->evaluateJohnTree( $rc, $sp, $newJohns, $johnTreeNode->branches, $path.".".$k, $results );
		}
	}
	
	////
	
	public function getItem( EarthIT_Schema_ResourceClass $resourceClass, $itemId ) {
		$params = array('table' => $this->rcTableExpression( $resourceClass ));
		$whereClauses = self::encodeColumnValuePairs($this->itemIdToColumnValues( $resourceClass, $itemId ), $params);
		$rows = $this->fetchRows( "SELECT * FROM {table}\nWHERE ".implode("\n  AND ",$whereClauses), $params );
		if( count($rows) == 1 ) return $rows[0];
		if( count($rows) == 0 ) return null;
		throw new Exception("Getting an item by ID returned multiple rows.");
	}
	
	public function search(
		EarthIT_Schema_ResourceClass $rc,
		EarthIT_CMIPREST_SearchParameters $sp,
		array $branches
	) {
		$results = array();
		$this->evaluateJohnTree( $rc, $sp, array(), $branches, 'root', $results );
		return $results;
	}

	public function postItem( EarthIT_Schema_ResourceClass $rc, array $itemData ) {
		throw new Exception("Not yet implemented");
	}
	
	public function putItem( EarthIT_Schema_ResourceClass $rc, $itemId, array $itemData ) {
		throw new Exception("Not yet implemented");
	}
	
	public function patchItem( EarthIT_Schema_ResourceClass $rc, $itemId, array $itemData ) {
		throw new Exception("Not yet implemented");
	}
}
