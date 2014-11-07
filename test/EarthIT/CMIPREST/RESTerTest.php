<?php

class EarthIT_CMIPREST_UserAction_WackAction extends EarthIT_CMIPREST_UserAction
{
	public function getActionDescription() {
		return "This action doesn't mean anything.";
	}
}

class EarthIT_CMIPREST_RESTerTest extends PHPUnit_Framework_TestCase
{
	protected $storage;
	protected $rester;
	protected $schema;
	
	protected $savedItems;
	
	public function setUp() {
		// Relative to the pwd, yes.
		$dbConfigFile = 'config/test-dbc.json';
		$dbConfigJson = file_get_contents($dbConfigFile);
		if( $dbConfigJson === null ) {
			throw new Exception("Failed to load database config from $dbConfigFile");
		}
		$dbConfig = EarthIT_JSON::decode($dbConfigJson);
		$this->schema = require 'test-schema.php';
		$this->storage = new EarthIT_CMIPREST_MemoryStorage();
		$this->rester = new EarthIT_CMIPREST_RESTer(array(
			'storage' => $this->storage,
			'schema' => $this->schema
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
			$getItemResult = $this->rester->doAction( new EarthIT_CMIPREST_UserAction_GetItemAction(0, $rc, $savedItem['ID'], array() ) );
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
		$posted = $this->rester->doAction( EarthIT_CMIPREST_UserActions::multiPost(0, $rc, $items) );
		for( $i=0; $i<5; ++$i ) {
			$this->assertNotNull($posted[$i]['id']);
			$this->assertEquals($items[$i]['URN'], $posted[$i]['urn']);
		}
	}
	
	public function testPostItem() {
		$rc = $this->schema->getResourceClass('resource');
		
		$urn = 'data:text/plain,'.rand(1000000,9999999);
		$posted = $this->rester->doAction( new EarthIT_CMIPREST_UserAction_PostItemAction(0, $rc, array('URN'=>$urn)) );
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
		$this->rester->doAction( new EarthIT_CMIPREST_UserAction_DeleteItemAction(0, $rc, $id) );
		
		// Now make sure it's gone!
		$this->assertNull( $this->getItem($rc, $id) );
	}
	
	public function testMultiPost2() {
		$rc = $this->schema->getResourceClass('person');
		
		$multiPost = EarthIT_CMIPREST_UserActions::multiPost(0, $rc, array(
			array(
				'first name' => 'Bob',
				'last name' => 'Hope'
			),
			array(
				'first name' => 'Red',
				'last name' => 'Skelton'
			),
		));
		
		$rez = $this->rester->doAction($multiPost);
		$bobHopeId = $rez[0]['id'];
		$redSkeltonId = $rez[1]['id'];
		
		$items = $this->storage->search($rc, new EarthIT_CMIPREST_SearchParameters(
			array('ID' => new EarthIT_CMIPREST_FieldMatcher_In(array($bobHopeId, $redSkeltonId))),
			array(), 0, null ), array())['root'];
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
		
		$multiPatch = EarthIT_CMIPREST_UserActions::multiPatch(0, $rc, array(
			$personA['ID'] => array(
				'first name' => 'Fred'
			),
			$personB['ID'] => array(
				'first name' => 'Frank'
			)
		));
		
		$rez = $this->rester->doAction($multiPatch);
		$this->assertEquals( array(
			array(
				'id' => $personA['ID'],
				'firstName' => 'Fred',
				'lastName' => 'Smith',
			),
			array(
				'id' => $personB['ID'],
				'firstName' => 'Frank',
				'lastName' => 'Smith',
			)
		), $rez);
		
		$items = $this->storage->search($rc, new EarthIT_CMIPREST_SearchParameters(
			array('ID' => new EarthIT_CMIPREST_FieldMatcher_In(array($personA['ID'], $personB['ID']))),
			array(), 0, null ), array())['root'];
		foreach( $items as $item ) {
			if( $item['ID'] == $personA['ID'] ) {
				$this->assertEquals('Fred', $item['first name']);
			}
			if( $item['ID'] == $personB['ID'] ) {
				$this->assertEquals('Frank', $item['first name']);
			}
		}
	}
	
	//// Test CMIPRESTRequest parsing
	
	public function testParseMultiPatch() {
		$crr = EarthIT_CMIPREST_CMIPRESTRequest::parse('PATCH', '/people', array(), array(
			3 => array(
				'firstName' => 'Jake',
				'lastName' => 'Wagner'
			),
			7 => array(
				'firstName' => 'Jeff',
				'lastName' => 'Glaze'
			)
		));
		$crr->userId = 123;
		
		$ua = $this->rester->cmipRequestToUserAction($crr);
		
		$this->assertEquals( EarthIT_CMIPREST_UserActions::multiPatch(
			123,
			$this->schema->getResourceClass('person'),
			array(
				3 => array(
					'first name' => 'Jake',
					'last name' => 'Wagner'
				),
				7 => array(
					'first name' => 'Jeff',
					'last name' => 'Glaze'
				)
			)
		), $ua);
	}
	
	//// Test some errors
	
	public function testInvalidActionError() {
		$errorDetail = array(
			'class'=>'CientError/ActionInvalid/Test',
			'message'=>'This action should always fail!'
		);
		$act = new EarthIT_CMIPREST_UserAction_InvalidAction( 0, array( $errorDetail ) );
		$rez = $this->rester->_r78($act);
		$responseString = (string)$rez->getContent();
		$responseObject = json_decode($responseString, true);
		$this->assertEquals( array( 'errors' => array($errorDetail) ), $responseObject );
	}
	
	public function testWackActionError() {
		$act = new EarthIT_CMIPREST_UserAction_WackAction( 0 );
		$rez = $this->rester->_r78($act);
		$responseString = (string)$rez->getContent();
		$responseObject = json_decode($responseString, true);
		$this->assertEquals( array( 'errors' => array(array(
			'class'=>'ClientError/ActionInvalid',
			'message'=>'Unrecognized action class: EarthIT_CMIPREST_UserAction_WackAction'
		)) ), $responseObject );
	}
}
