<?php

abstract class EarthIT_CMIPREST_UserAction
{
	protected $userId;
	
	public function __construct( $userId ) {
		$this->userId = $userId;
	}
	
	public function getUserId() {
		return $this->userId;
	}
	
	/**
	 * Returns a verb-phrase that describes what is being done
	 * which could complete a sentence like
	 * 'So and so is trying to ...' or
	 * 'You are not allowed to ...',
	 * e.g. "pick apples", "pick at the Mona Lisa"
	 */
	public abstract function getActionDescription();
}
