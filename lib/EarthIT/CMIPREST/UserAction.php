<?php

// TODO: Refactor to NOT include user ID.
// TODO: Get rid of options; just make assembler an additional
// parameter of applicable action constructors
abstract class EarthIT_CMIPREST_UserAction
{
	// Keys for options arrays
	/**
	 * Indicate which ResultAssembler to use.
	 * If null, results will not be returned.
	 */
	const OPT_RESULT_ASSEMBLER = 'result-assembler';
	
	protected $userId;
	/**
	 * Array of options; see above constants
	 */
	protected $options;
	
	/**
	 * @param array $opts array of options; see above
	 */
	public function __construct( $userId, array $opts=array() ) {
		$this->userId = $userId;
		$this->options = $opts;
	}
	
	/**
	 * @return the ID of the user on whose behalf the action is being performed.
	 */
	public function getUserId() {
		return $this->userId;
	}
	
	public function getOptions() {
		return $this->options;
	}
	
	public function getOption($k, $default=null) {
		return isset($this->options[$k]) ? $this->options[$k] : $default;
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
