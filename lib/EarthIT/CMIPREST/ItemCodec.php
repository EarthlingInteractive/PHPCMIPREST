<?php

/**
 * A transformation that can be done or undone to transform between
 * two forms of item data.  The intention is that both forms will be a
 * subset of 'schema form', but with certain fields added or removed.
 * 
 * For example, if we want to represent someone's blood pressure as
 * "117/76" internally, but as 2 separate columns in the database,
 * the resource class would declare 3 fields:
 * 
 * - blood pressure : string : has a database table @ false
 * - systolic blood pressure : integer
 * - diastolic blood pressure : integer
 * 
 * In this case an ItemCodec could be defined to represent the
 * transformation between 'internal schema form' and 'DB-ish schema
 * form'.  #decode would combine and remove the systolic and diastolic
 * field values, and #encode would split the combined field into the
 * other 2.
 * 
 * Alternatively, you could consider the 2-field form the 'internal'
 * one, and have your REST request parser and response assemblers do
 * the translation.  In this case the 'coding' is the API form, so
 * 'encode' encodes an internal form item to API-ish form, and decode
 * decodes API-ish form to internal form.
 * 
 * i.e. 'internal schema form' is the canonical form which 'gets encoded'.
 * 
 *       ╭──────────────────────╮
 *       │ API-ish schema form  │
 *       ╰──────────────────────╯
 *         ↑ encode  ↓ decode
 *       ╭──────────────────────╮
 *       │ internal schema form │
 *       ╰──────────────────────╯
 *         ↓ encode  ↑ decode
 *       ╭──────────────────────╮
 *       │ DB-ish schema form   │
 *       ╰──────────────────────╯
 */
interface EarthIT_CMIPREST_ItemCodec
{
	public function encodeItems( array $items, EarthIT_Schema_ResourceClass $rc );
	public function decodeItems( array $items, EarthIT_Schema_ResourceClass $rc );
}
