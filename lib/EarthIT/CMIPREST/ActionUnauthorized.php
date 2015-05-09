<?php

class EarthIT_CMIPREST_ActionUnauthorized extends Exception
{
	protected $action;
	protected $context;
	protected $notes;
	
	public function __construct( EarthIT_CMIPREST_RESTAction $action, $ctx, array $notes=array() ) {
		$this->action = $action;
		$this->context = $ctx;
		$this->notes = $notes;
		$message = "You are not authorized to ".$action->getActionDescription();
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
	
	public function getAction() { return $this->action; }
	public function getContext() { return $this->context; }
	public function getNotes() { return $this->notes; }
}
