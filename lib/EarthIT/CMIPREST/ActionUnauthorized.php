<?php

class EarthIT_CMIPREST_ActionUnauthorized extends Exception
{
	/** The message without notes and stuff glommed on. */
	protected $simpleMessage;
	protected $action;
	protected $context;
	protected $notes;
	
	public function __construct( EarthIT_CMIPREST_RESTAction $action, $ctx, array $notes=array() ) {
		$this->action = $action;
		$this->context = $ctx;
		$this->notes = $notes;
		$message = "You are not authorized to ".$action->getActionDescription();
		$this->simpleMessage = $message;
		if( $notes ) {
			$message .= " because:";
			foreach( $notes as $note ) {
				$message .= "\n- $note";
			}
		} else {
			$message .= '.';
		}
		parent::__construct( $message );
	}
	
	public function getSimpleMessage() { return $this->simpleMessage; }
	public function getAction() { return $this->action; }
	public function getContext() { return $this->context; }
	public function getNotes() { return $this->notes; }
}
