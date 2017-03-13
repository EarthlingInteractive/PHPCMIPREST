<?php

class EarthIT_CMIPREST_ActionStringificationTest extends EarthIT_CMIPREST_TestCase {
	public function testStringifySomething() {
		$act = new EarthIT_CMIPREST_RESTAction_GetItemAction(
			$this->rc('resource'), '1234', array(),
			new EarthIT_CMIPREST_ResultAssembler_NOJResultAssembler('assembleSuccessResult'));
		
		$actStr = (string)$act;
		$this->assertEquals(
			"{\n".
			"\t\"phpClassName\": \"EarthIT_CMIPREST_RESTAction_GetItemAction\",\n".
			"\t\"resourceClassName\": \"resource\",\n".
			"\t\"itemId\": \"1234\",\n".
			"\t\"johnBranches\": [],\n".
			"\t\"resultAssembler\": {}\n".
			"}",
			$actStr
		);
	}
}
