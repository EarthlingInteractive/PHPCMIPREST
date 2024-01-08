<?php

/**
 * 'Do this action with the permissions of these users'
 */
class EarthIT_CMIPREST_RESTAction_SudoAction extends EarthIT_CMIPREST_RESTAction
{
	const PMM_REPLACE = 'Replace'; // Sudoed permissions replace current permissions
	const PMM_ADD = 'Add'; // Sudoed permissions are added to current permissions
	
	protected $action;
	protected $suIds;
	protected $permissionMergeMode;
	public function __construct( EarthIT_CMIPREST_Action $action, array $suIds, $permissionMergeMode=self::PMM_ADD ) {
		$this->action = $action;
		$this->suIds = $suIds;
		$this->permissionMergeMode = $permissionMergeMode;
	}
	
	public function getAction() { return $this->action; }
	public function getSuIds() { return $this->suIds; }
	public function getPermissionMergeMode() { return $this->permissionMergeMode; }
	
	public function getActionDescription() {
		return $this->action->getActionDescription()." with permissions of ".implode(', ',$this->suIds);
	}
	public function getResultAssembler() {
		return $this->action->getResultAssembler();
	}
}
