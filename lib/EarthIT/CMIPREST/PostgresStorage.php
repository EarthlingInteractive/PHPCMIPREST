<?php

class EarthIT_CMIPREST_PostgresStorage implements EarthIT_CMIPREST_Storage
{
	protected $dbAdapter;
	protected $schema;
	protected $dbNamer;
	
	public function __construct( Doctrine\DBAL\Connection $dbAdapter, EarthIT_Schema $schema, EarthIT_DBC_Namer $dbNamer) {
		$this->dbAdapter = $dbAdapter;
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
		$components[] = new EarthIT_DBC_SQLIdentifier($rc->getTableNameOverride() ?: $this->dbNamer->getTableName($rc));
		return new EarthIT_DBC_SQLNamespacePath($components);
	}
	
	/** i.e. 'name of column corresponding to field' */
	protected function fieldDbName( EarthIT_Schema_ResourceClass $rc, EarthIT_Schema_Field $f ) {
		return $f->getColumnNameOverride() ?: $this->dbNamer->getColumnName( $rc, $f );
	}
	
	//// Conversion
	
	protected static function valuesOfTypeShouldBeSelectedAsGeoJson( EarthIT_Schema_DataType $t ) {
		return
			preg_match('/^(GEOMETRY|GEOGRAPHY)(\(|$)/', $t->getSqlTypeName()) &&
			$t->getPhpTypeName() == 'GeoJSON array';
	}
	
	protected static function dbToPhpValue( EarthIT_Schema_DataType $t, $value ) {
		if( self::valuesOfTypeShouldBeSelectedAsGeoJson($t) ) {
			return $value === null ? null : EarthIT_JSON::decode($value);
		}
		// Various special rules may end up here
		return EarthIT_CMIPREST_Util::cast( $value, $t->getPhpTypeName() );
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
	
	protected function buildSelects( EarthIT_Schema_ResourceClass $rc ) {
		$selects = array();
		foreach( $rc->getFields() as $f ) {
			$columnName = $this->fieldDbName($rc, $f);
			// TODO: parameterize column names
			if( self::valuesOfTypeShouldBeSelectedAsGeoJson($f->getType()) ) {
				$selects[] = "ST_AsGeoJSON({$columnName}) AS $columnName";
			} else {
				$selects[] = $columnName;
			}
		}
		return $selects;
	}
	
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
		$sql = "SELECT ".implode(', ',$this->buildSelects($rc))." FROM {table} AS {$tableAlias}";
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
		
		$params = array();
		$rootSql = $this->buildSearchSql( $rc, $sp, $params );
		if( $rootSql == 'SELECT NOTHING' ) return;
		
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
		
		$sql =
			"SELECT {$targetAlias}.* FROM (\n".
			"\t".str_replace("\n","\n\t",trim($rootSql))."\n".
			") AS {$rootAlias}";
		
		if( count($joins) > 0 ) {
			$sql .= "\n".implode("\n",$joins);
		}
		
		foreach( $this->fetchRows($sql, $params) AS $dbObj ) {
			$results[$path][] = $this->dbObjectToInternal($targetRc, $dbObj);
		}
	}
	
	////
	
	public function getItem( EarthIT_Schema_ResourceClass $rc, $itemId ) {
		$params = array('table' => $this->rcTableExpression( $rc ));
		$whereClauses = self::encodeColumnValuePairs($this->itemIdToColumnValues( $rc, $itemId ), $params);
		$rows = $this->fetchRows( "SELECT * FROM {table}\nWHERE ".implode("\n  AND ",$whereClauses), $params );
		if( count($rows) == 1 ) return $this->dbObjectToInternal($rc, $rows[0]);
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
		$tableExpression = $this->rcTableExpression( $rc );

		$params = array();
		$columnValues = $this->internalObjectToDb($rc, $itemData, $params);
		$columnExpressionList = array();
		$columnValueList = array();
		foreach( $columnValues as $columnName => $value ) {
			$columnExpressionList[] = new EarthIT_DBC_SQLIdentifier($columnName);
			$columnValueList[] = $value;
		}
		
		// TODO: actually determine ID columns
		
		$rows = $this->fetchRows("INSERT INTO {table} {columns}\nVALUES {values}\nRETURNING *", array_merge(array(
			'table' => $tableExpression,
			'columns' => $columnExpressionList,
			'values' => $columnValueList
		), $params));
		if( count($rows) != 1 ) {
			throw new Exception("INSERT INTO ... RETURNING returned ".count($rows)." rows; expected exactly 1.");
		}
		foreach( $rows as $row ) {
			return $this->dbObjectToInternal($rc, $row);
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
		
		$params = array('table' => $this->rcTableExpression($rc));
		$columnValues = $this->internalObjectToDb($rc, $internalValues, $params);

		$conditions = self::encodeColumnValuePairs($this->internalObjectToDb($rc, $idFieldValues, $params), $params);
		$sets       = self::encodeColumnValuePairs($columnValues, $params);
		
		$params['columns'] = array();
		$values = array();
		foreach( $columnValues as $colName => $value ) {
			$params['columns'][] = new EarthIT_DBC_SQLIdentifier($colName);
			$valueParamName = EarthIT_DBC_ParameterUtil::newParamName('v');
			$valueSelects[] = "{{$valueParamName}}";
			$params[$valueParamName] = $value;
		}
		
		$selects = implode(', ',$this->buildSelects($rc));
		$sql =
			"WITH los_updatos AS (\n".
			"\t"."UPDATE {table} SET\n".
			"\t\t".implode(",\n\t\t",$sets)."\n".
			"\t"."WHERE ".implode("\n\t  AND",$conditions)."\n".
			"\t"."RETURNING {$selects}\n".
			"), los_insertsos AS (\n".
			"\t"."INSERT INTO {table} {columns}\n".
			"\t"."SELECT ".implode(", ",$valueSelects)."\n".
			"\t"."WHERE NOT EXISTS ( SELECT * FROM los_updatos )\n".
			"\t"."RETURNING {$selects}\n".
			")\n".
			"SELECT * FROM los_updatos UNION ALL SELECT * FROM los_insertsos";
		$rows = $this->fetchRows( $sql, $params );
		if( count($rows) != 1 ) {
			throw new Exception("UPDATE ... RETURNING returned ".count($rows)." rows; expected exactly 1.");
		}
		foreach( $rows as $row ) {
			return $this->dbObjectToInternal($rc, $row);
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
		$this->fetchRows( $sql, $params );
		// Nothing to return
	}
}
