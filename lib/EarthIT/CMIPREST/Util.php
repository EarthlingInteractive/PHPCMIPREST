<?php

class EarthIT_CMIPREST_Util
{
	/**
	 * Convert a value to the named PHP scalar type using PHP's default conversion.
	 */
	public static function cast( $value, $phpType ) {
		if( $phpType === null or $value === null ) return $value;
		
		switch( $phpType ) {
		case 'string': return (string)$value;
		case 'float': return (float)$value;
		case 'int': return (int)$value;
		case 'bool': return (bool)$value;
		default:
			throw new Exception("Don't know how to cast to PHP type '$phpType'.");
		}
	}
	
	public static function getIdRegex( EarthIT_Schema_ResourceClass $rc ) {
		$pk = $rc->getPrimaryKey();
		if( $pk === null or count($pk->getFieldNames()) == 0 ) {
			throw new Exception("No ID regex because no primary key for ".$rc->getName().".");
		}
		
		$fields = $rc->getFields();
		$parts = array();
		foreach( $pk->getFieldNames() as $fn ) {
			$field = $fields[$fn];
			$datatype = $field->getType();
			$fRegex = $datatype->getRegex();
			if( $fRegex === null ) {
				throw new Exception("Can't build ID regex because ID component field '$fn' is of type '".$datatype->getName()."', which doesn't have a regex.");
			}
			$parts[] = "($fRegex)";
		}
		return implode("-", $parts);
	}
	
	/**
	 * return array of field name => field value for the primary key fields encoded in $id
	 */
	public static function idToFieldValues( EarthIT_Schema_ResourceClass $rc, $id) {
		$idRegex = self::getIdRegex( $rc );
		if( !preg_match('/^'.$idRegex.'$/', $id, $bif) ) {
			throw new Exception("ID did not match regex /^$idRegex\$/: $id");
		}
		
		$idFieldValues = array();
		$pk = $rc->getPrimaryKey();
		$fields = $rc->getFields();
		$i = 1;
		foreach( $pk->getFieldNames() as $fn ) {
			$field = $fields[$fn];
			$idFieldValues[$fn] = EarthIT_CMIPREST_Util::cast($bif[$i], $field->getType()->getPhpTypeName());
			++$i;
		}
		
		return $idFieldValues;
	}
		
	public static function mergeEnsuringNoContradictions() {
		$arrays = func_get_args();
		$result = array();
		foreach( $arrays as $arr ) {
			foreach( $arr as $k=>$v ) {
				if( isset($result[$k]) ) {
					if( $result[$k] !== $v ) {
						throw new Exception("Conflicting values given for '$k': ".json_encode($result[$k]).", ".json_encode($v));
					}
				} else {
					$result[$k] = $v;
				}
			}
		}
		return $result;
	}
}
