<?php

class EarthIT_CMIPREST_RESTerTest_Context
{
	public $userId;
	
	public function __construct($userId) { $this->userId = $userId; }
}

class EarthIT_CMIPREST_RESTAction_WackAction extends EarthIT_CMIPREST_RESTAction
{
	protected $resultAssembler;
	
	public function __construct(EarthIT_CMIPREST_ResultAssembler $rasm) {
		$this->resultAssembler = $rasm;
	}
	
	public function getActionDescription() {
		return "This action doesn't mean anything.";
	}
	
	public function getResultAssembler() {
		return $this->resultAssembler;
	}
}

class EarthIT_CMIPREST_RESTerTest extends EarthIT_CMIPREST_TestCase
{
	protected $storage;
	protected $rester;
	protected $schema;

	protected $standardSaveActionResultAssembler;
	protected $standardSuccessActionResultAssembler;
	protected $standardDeleteActionResultAssembler;
	
	protected $savedItems;
	
	public function setUp() {
		$this->standardSaveActionResultAssembler =
			new EarthIT_CMIPREST_ResultAssembler_NOJResultAssembler('assembleSingleResult', false);
		$this->standardSuccessActionResultAssembler =
			new EarthIT_CMIPREST_ResultAssembler_NOJResultAssembler('assembleSuccessResult', false);
		$this->standardDeleteActionResultAssembler =
			new EarthIT_CMIPREST_ResultAssembler_NOJResultAssembler('assembleDeleteResult', false);
		$this->standardActionContext = new EarthIT_CMIPREST_RESTerTest_Context(1337);

		// Relative to the pwd, yes.
		$dbConfigFile = $this->configDir.'/dbc.json';
		$dbConfigJson = file_get_contents($dbConfigFile);
		if( $dbConfigJson === null ) {
			throw new Exception("Failed to load database config from $dbConfigFile");
		}
		$dbConfig = EarthIT_JSON::decode($dbConfigJson);
		$this->schema = $this->loadTestSchema();
		$this->storage = new EarthIT_CMIPREST_MemoryStorage();
		$this->rester = new EarthIT_CMIPREST_RESTer(array(
			'storage' => $this->storage,
			'schema' => $this->schema,
			'authorizer' => new EarthIT_CMIPREST_RESTActionAuthorizer_Doormat()
		));
		
		$rc = $this->schema->getResourceClass('resource');
		$this->savedItems = array(
			$this->storage->postItem( $rc, array('URN'=>'data:text/plain,'.rand(1000000,9999999))),
			$this->storage->postItem( $rc, array('URN'=>'data:text/plain,'.rand(1000000,9999999)))
		);
	}
	
	protected function getItem( $rc, $id ) {
		return EarthIT_CMIPREST_Util::getItemById($this->storage, $rc, $id);
	}
	
	public function testGetItem() {
		$rc = $this->schema->getResourceClass('resource');
		
		foreach( $this->savedItems as $savedItem ) {
			$getItemResult = $this->rester->doAction(
				new EarthIT_CMIPREST_RESTAction_GetItemAction(
					$rc, $savedItem['ID'], array(), $this->standardSaveActionResultAssembler ),
				$this->standardActionContext);
			$this->assertEquals($savedItem['ID'], $getItemResult['id']);
			$this->assertEquals($savedItem['URN'], $getItemResult['urn']);
		}
	}
	
	public function testMultiPost1() {
		$rc = $this->schema->getResourceClass('resource');
		
		$items = array();
		for( $i=0; $i<5; ++$i ) {
			$items[] = array('URN'=>'data:text/plain,'.rand(1000000,9999999));
		}
		$posted = $this->rester->doAction(
			EarthIT_CMIPREST_RESTActions::multiPost(
				$rc, $items, $this->standardSaveActionResultAssembler),
			$this->standardActionContext);
		for( $i=0; $i<5; ++$i ) {
			$this->assertNotNull($posted[$i]['id']);
			$this->assertEquals($items[$i]['URN'], $posted[$i]['urn']);
		}
	}
	
	public function testPostItem() {
		$rc = $this->schema->getResourceClass('resource');
		
		$urn = 'data:text/plain,'.rand(1000000,9999999);
		$posted = $this->rester->doAction(
			new EarthIT_CMIPREST_RESTAction_PostItemAction(
				$rc, array('URN'=>$urn), $this->standardSaveActionResultAssembler ),
			$this->standardActionContext);
		$got = $this->getItem($rc, $posted['id']);
		$this->assertEquals($urn, $got['URN']);
	}
	
	public function testDeleteItem() {
		$rc = $this->schema->getResourceClass('resource');
		$urn = 'data:text/plain,'.rand(1000000,9999999);
		$posted = $this->storage->postItem( $rc, array('URN'=>$urn) );
		$id = $posted['ID'];
		$got = $this->getItem($rc, $id);
		$this->assertEquals($urn, $got['URN']);
		
		// Now baleete it!
		$this->rester->doAction(
			new EarthIT_CMIPREST_RESTAction_DeleteItemAction($rc, $id, $this->standardDeleteActionResultAssembler),
			$this->standardActionContext );
		
		// Now make sure it's gone!
		$this->assertNull( $this->getItem($rc, $id) );
	}
	
	public function testMultiPost2() {
		$rc = $this->schema->getResourceClass('person');
		
		$multiPost = EarthIT_CMIPREST_RESTActions::multiPost($rc, array(
			array(
				'first name' => 'Bob',
				'last name' => 'Hope'
			),
			array(
				'first name' => 'Red',
				'last name' => 'Skelton'
			),
		), $this->standardSaveActionResultAssembler);
		
		$rez = $this->rester->doAction($multiPost, $this->standardActionContext);
		$bobHopeId = $rez[0]['id'];
		$redSkeltonId = $rez[1]['id'];
		
		$rez = $this->storage->johnlySearchItems(
			new EarthIT_Storage_Search(
				$rc,
				EarthIT_Storage_ItemFilters::byId(array($bobHopeId, $redSkeltonId), $rc),
				EarthIT_Storage_FieldwiseComparator::parse('+ID')
			),
			array(), array());
		$items = $rez['root'];
		$this->assertEquals( array(
			array(
				'ID' => $bobHopeId,
				'first name' => 'Bob',
				'last name' => 'Hope'
			), array(
				'ID' => $redSkeltonId,
				'first name' => 'Red',
				'last name' => 'Skelton'
			)), $items);
	}
	
	public function testMultiPatch() {
		$rc = $this->schema->getResourceClass('person');
		
		$personA = $this->storage->postItem( $rc, array('first name'=>'Bob', 'last name'=>'Smith') );
		$personB = $this->storage->postItem( $rc, array('first name'=>'Ben', 'last name'=>'Smith') );
		
		$multiPatch = EarthIT_CMIPREST_RESTActions::multiPatch($rc, array(
			$personA['ID'] => array(
				'first name' => 'Fred'
			),
			$personB['ID'] => array(
				'first name' => 'Frank'
			)
		), $this->standardSaveActionResultAssembler );
		
		$rez = $this->rester->doAction($multiPatch, $this->standardActionContext);
		$this->assertEquals( array(
			$personA['ID'] => array(
				'id' => $personA['ID'],
				'firstName' => 'Fred',
				'lastName' => 'Smith',
			),
			$personB['ID'] => array(
				'id' => $personB['ID'],
				'firstName' => 'Frank',
				'lastName' => 'Smith',
			)
		), $rez);
		
		$rez = $this->storage->johnlySearchItems(
			new EarthIT_Storage_Search(
				$rc,
				EarthIT_Storage_ItemFilters::byId(array($personA['ID'], $personB['ID']), $rc)
			),
			array(), array());
		$items = $rez['root'];
		foreach( $items as $item ) {
			if( $item['ID'] == $personA['ID'] ) {
				$this->assertEquals('Fred', $item['first name']);
			}
			if( $item['ID'] == $personB['ID'] ) {
				$this->assertEquals('Frank', $item['first name']);
			}
		}
	}
	
	//// Test some errors
	
	public function testInvalidActionError() {
		$errorDetail = array(
			'class'=>'ClientError/ActionInvalid/ThisRandomStringIsExpected',
			'message'=>'This action should always fail!'
		);
		$act = new EarthIT_CMIPREST_RESTAction_InvalidAction( array( $errorDetail ), $this->standardSuccessActionResultAssembler );
		$rez = $this->rester->doActionAndGetHttpResponse($act, $this->standardActionContext);
		$responseString = (string)$rez->getContent();
		$responseObject = json_decode($responseString, true);
		$this->assertEquals( array( 'errors' => array($errorDetail) ), $responseObject );
	}
	
	public function testWackActionError() {
		$errorDetail = array(
			'class'=>'ClientError/ActionInvalid',
			'message'=>'Unrecognized action class: EarthIT_CMIPREST_RESTAction_WackAction'
		);
		$act = new EarthIT_CMIPREST_RESTAction_WackAction( $this->standardSuccessActionResultAssembler );
		$rez = $this->rester->doActionAndGetHttpResponse($act, $this->standardActionContext);
		$responseString = (string)$rez->getContent();
		$responseObject = json_decode($responseString, true);
		$this->assertEquals( array( 'errors' => array($errorDetail) ), $responseObject );
	}
}
