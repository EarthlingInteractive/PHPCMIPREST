<?php

// TODO: Replace FieldMatcher with 'filters' that work on records.
// FieldMatchers will become field filters and will know which field they're working on.
// Also, it really ought to be up do the upper database abstraction
// layer to translate these to SQL rather then them translating
// themselves, as their SQL could be different from database to
// database.

interface EarthIT_CMIPREST_FieldMatcher
{
	public function toSql( $fieldValueSql, $fieldType, &$params );
	public function matches( $fieldValue );
}
