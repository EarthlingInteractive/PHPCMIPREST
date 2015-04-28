<?php

class EarthIT_CMIPREST_MSSQLStorage implements EarthIT_CMIPREST_Storage
{
	const ATTR_IS_AUTO_INCREMENTED = "http://ns.nuke24.net/Schema/RDB/isAutoIncremented";
	
	protected $sqlRunner;
	protected $schema;
	protected $dbNamer;
	
	public function __construct( EarthIT_DBC_SQLRunner $sqlRunner, EarthIT_Schema $schema, EarthIT_DBC_Namer $dbNamer) {
		$this->sqlRunner = $sqlRunner;
		$this->schema = $schema;
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
		// TODO for breaking release: Remove getTableNameOverride; the namer should do that.
		$components[] = new EarthIT_DBC_SQLIdentifier($rc->getTableNameOverride() ?: $this->dbNamer->getTableName($rc));
		return new EarthIT_DBC_SQLNamespacePath($components);
	}
	
	/** i.e. 'name of column corresponding to field' */
	protected function fieldDbName( EarthIT_Schema_ResourceClass $rc, EarthIT_Schema_Field $f ) {
		// TODO for breaking release: Remove getTableNameOverride; the namer should do that.
		return $f->getColumnNameOverride() ?: $this->dbNamer->getColumnName( $rc, $f );
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
	
	protected static function phpToDbValue( EarthIT_Schema_DataType $t, $value ) {
		if( is_bool($value) ) {
			return $value ? 1 : 0;
		} else {
			return $value;
		}
	}
	
	protected function internalObjectToDb( EarthIT_Schema_ResourceClass $rc, array $obj, array &$params ) {
		$columnNamer = $this->dbNamer;
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
					$dbValue = self::phpToDbValue($f->getType(), $value);
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
	
	//// Do stuff
	
	protected function fetchRows( $sql, array $params=array() ) {
		if( $sql == 'SELECT NOTHING' ) return array();
		try {
			return $this->sqlRunner->fetchRows($sql, $params);
		} catch( PDOException $e ) {
			$debugSql = $this->sqlRunner->quoteParams($sql, $params);
			throw new Exception("Error while running query: $debugSql", 0, $e);
		}
	}
	
	protected function fetchRow( $sql, array $params=array(), $default=null ) {
		foreach( $this->fetchRows($sql, $params) as $row ) return $row;
		return $default;
	}
	
	protected function fetchValue( $sql, array $params=array(), $default=null ) {
		foreach( $this->fetchRow($sql, $params, array()) as $v ) return $v;
		return $default;
	}
	
	/** Same as fetchRows; just doesn't return anything. */
	protected function doQuery( $sql, array $params=array() ) {
		try {
			//echo $this->sqlRunner->quoteParams($sql, $params);
			return $this->sqlRunner->doQuery($sql, $params);
		} catch( PDOException $e ) {
			$debugSql = $this->sqlRunner->quoteParams($sql, $params);
			throw new Exception("Error while running query: $debugSql", 0, $e);
		}
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
				$orderBySqlComponents[] = $this->fieldDbName($rc, $oc->getField()).($oc->isAscending() ? " ASC" : " DESC");
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

		foreach( $this->fetchRows($sql, $params) AS $dbObj ) {
			$results[$path][] = $this->dbObjectToInternal($targetRc, $dbObj);
		}
	}
	
	////
	
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
		$tableExpression = $this->rcTableExpression( $rc );

		$params = array();
		$valuex = array();
		$columnValues = $this->internalObjectToDb($rc, $itemData, $params);
		$columnExpressionList = array();
		foreach( $columnValues as $columnName => $value ) {
			$valueParamName = EarthIT_DBC_ParameterUtil::newParamName('v');
			$valuex[] = "{{$valueParamName}}";
			$params[$valueParamName] = $value;
			$columnExpressionList[] = new EarthIT_DBC_SQLIdentifier($columnName);
			$columnValueList[] = $value;
		}
		
		// TODO: actually determine ID columns
		// So we can validate or something?
		
		$params['table'] = $tableExpression;
		$params['columns'] = $columnExpressionList;
		$rows = $this->doQuery(
			"INSERT INTO {table}\n".
			"{columns} VALUES\n".
			"(".implode(',',$valuex).")", $params);
		
		$resultNeeded = true;
		if( $resultNeeded ) {
			$pkFieldCount = 0;
			$autoIncrementFieldName = null;
			// figure out how to query for the thing we just inserted
			foreach( $rc->getPrimaryKey()->getFieldNames() as $fn ) {
				++$pkFieldCount;
				$f = $rc->getField($fn);
				if( $f->getFirstPropertyValue(self::ATTR_IS_AUTO_INCREMENTED) ) {
					$autoIncrementFieldName = $fn;
				}
			}
			
			if( $autoIncrementFieldName ) {
				// TODO: Actually get record back from database
				// so that defaults are all filled in.
				// Otherwise we're just kind of guessing.
				$itemData[$autoIncrementFieldName] = $this->fetchValue("SELECT SCOPE_IDENTITY()");
			}
			
			return $itemData;
		}
	}
	
	protected function doPatchLikeAction( EarthIT_Schema_ResourceClass $rc, $itemId, array $itemData, $merge ) {
		// TODO: Make it work even if the record does not already exist
		
		$idFieldValues = EarthIT_CMIPREST_Util::idToFieldValues( $rc, $itemId );
		$internalValues = EarthIT_CMIPREST_Util::mergeEnsuringNoContradictions( $idFieldValues, $itemData );
		if( !$merge ) {
			// Set other field values to their defaults.
			// Assuming null for now...
			foreach( $rc->getFields() as $fieldName => $field ) {
				if( !isset($internalValues[$fieldName]) ) {
					$internalValues[$fieldName] = null;
				}
			}
		}
		
		$nonIdInternalValues = $internalValues;
		foreach( $rc->getPrimaryKey()->getFieldNames() as $fn ) {
			unset($nonIdInternalValues[$fn]);
		}
		
		$params = array('table' => $this->rcTableExpression($rc));
		$columnValues = $this->internalObjectToDb($rc, $internalValues, $params);
		$nonIdColumnValues = $this->internalObjectToDb($rc, $nonIdInternalValues, $params);
		
		$conditions = self::encodeColumnValuePairs($this->internalObjectToDb($rc, $idFieldValues, $params), $params);
		$sets       = self::encodeColumnValuePairs($nonIdColumnValues, $params);
		
		$params['columns'] = array();
		$values = array();
		foreach( $columnValues as $colName => $value ) {
			$values[] = $value;
			$params['columns'][] = new EarthIT_DBC_SQLIdentifier($colName);
			$valueParamName = EarthIT_DBC_ParameterUtil::newParamName('v');
			$valueSelects[] = "{{$valueParamName}}";
			$params[$valueParamName] = $value;
		}
		$params['values'] = $values;
		
		$selects = implode(', ',$this->buildSelects($rc, $params));
		$sql =
			"IF NOT EXISTS (SELECT * FROM {table} WHERE ".implode(" AND ",$conditions).")\n".
			"\tINSERT INTO {table}\n".
			"\t{columns} VALUES\n".
			"\t{values}\n".
			"ELSE\n".
			"\tUPDATE {table} SET\n".
			"\t\t".implode(",\n\t\t",$sets)."\n".
			"\tWHERE ".implode(" AND ",$conditions);
		$this->doQuery($sql, $params);
		
		$resultNeeded = true;
		if( $resultNeeded ) {
			$row = $this->fetchRow("SELECT * FROM {table} WHERE ".implode(" AND ",$conditions), $params);
			return $row === null ? null : $this->dbObjectToInternal($rc, $row);
		}
	}
	
	public function putItem( EarthIT_Schema_ResourceClass $rc, $itemId, array $itemData ) {
		return $this->doPatchLikeAction($rc, $itemId, $itemData, false);
	}
	
	public function patchItem( EarthIT_Schema_ResourceClass $rc, $itemId, array $itemData ) {
		return $this->doPatchLikeAction($rc, $itemId, $itemData, true);
	}
	
	public function deleteItem( EarthIT_Schema_ResourceClass $rc, $itemId ) {
		$idFieldValues = EarthIT_CMIPREST_Util::idToFieldValues( $rc, $itemId );
		$params = array('table' => $this->rcTableExpression($rc));
		$conditions = self::encodeColumnValuePairs($this->internalObjectToDb($rc, $idFieldValues, $params), $params);
		$sql =
			"DELETE FROM {table}\n".
			"WHERE ".implode("\n  AND ", $conditions);
		$this->doQuery( $sql, $params );
	}
}
