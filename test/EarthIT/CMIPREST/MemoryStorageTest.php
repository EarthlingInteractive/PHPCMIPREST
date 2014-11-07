<?php

class EarthIT_CMIPREST_MemoryStorageTest extends EarthIT_CMIPREST_StorageTest
{
	protected function createStorage() {
		return new EarthIT_CMIPREST_MemoryStorage();
	}
}
