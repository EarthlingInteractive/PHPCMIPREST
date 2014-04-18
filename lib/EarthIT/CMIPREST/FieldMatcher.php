<?php

interface EarthIT_CMIPREST_FieldMatcher
{
	public function toSql( $fieldValueSql, $fieldType, &$params );
}
