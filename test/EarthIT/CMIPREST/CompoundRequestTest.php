<?php

class EarthIT_CMIPREST_CompoundRequestTest extends EarthIT_CMIPREST_TestCase
{
	public function setUp() : void {
		$this->schema = $this->loadTestSchema();
		$this->schemaObjectNamer = EarthIT_CMIPREST_Namers::getStandardCamelCaseNamer();
		$this->memoryStorage = new EarthIT_CMIPREST_MemoryStorage();
		$this->rester = new EarthIT_CMIPREST_RESTer(array(
			'schema' => $this->schema,
			'storage' => $this->memoryStorage,
			'authorizer' => new EarthIT_CMIPREST_RESTActionAuthorizer_Doormat(),
		));
	}
	
	/**
	 * Whether this is implemented as a compound action or not,
	 * the end result should be the same.
	 */
	public function testDoMultiPost() {
		$requestParser = new EarthIT_CMIPREST_RequestParser_CMIPRequestParser($this->schema, $this->schemaObjectNamer);
		$req = $requestParser->parse('POST', '/people', '', new EarthIT_JSON_PrettyPrintedJSONBlob(array(
			array('firstName'=>'Bob','lastName'=>'Lindmeier'),
			array('firstName'=>'Bob','lastName'=>'Saget')
		)));
		$act = $requestParser->toAction($req);
		$asm = $this->rester->doAction($act, null);
		$this->assertTrue( is_array($asm), "Result of doAction should be an array, but got a ".gettype($asm) );
		$this->assertEquals( 2, count($asm), "There should be 2 items in the result" );
		foreach( $asm as $k => $item ) {
			$this->assertTrue( is_array($item), "Each item should itself be an array" );
			$this->assertTrue( array_key_exists('id', $item), "Each item should have an 'id' field" );
			$this->assertEquals( $k, $item['id'], "Each item's 'id' field should match the item's key" );
		}
	}
	
	public function testDoMultiPatch() {
		$this->memoryStorage->saveItems( array(
			array('ID'=>4, 'first name'=>'Bob','last name'=>'Lindmeier'),
			array('ID'=>5, 'first name'=>'Bob','last name'=>'Saget')
		), $this->schema->getResourceClass('person'), array(
			EarthIT_Storage_ItemSaver::RETURN_SAVED => true,
			EarthIT_Storage_ItemSaver::ON_DUPLICATE_KEY => EarthIT_Storage_ItemSaver::ODK_REPLACE
		));
		
		$patch = array(
			4 => array('firstName' => 'William'),
			5 => array('firstName' => 'Nissage') 
		);
		$requestParser = new EarthIT_CMIPREST_RequestParser_CMIPRequestParser($this->schema, $this->schemaObjectNamer);
		$req = $requestParser->parse('PATCH', '/people', '', new EarthIT_JSON_PrettyPrintedJSONBlob($patch));
		$act = $requestParser->toAction($req);
		$asm = $this->rester->doAction($act, null);
		$this->assertEquals( array(
			4 => array('id'=>4, 'firstName'=>'William', 'lastName'=>'Lindmeier'),
			5 => array('id'=>5, 'firstName'=>'Nissage', 'lastName'=>'Saget'),
		), $asm, "Result of multipatch should look like I think it ought to.");
	}
	
	protected function _testDoCompoundAction( $requestParser, $method, $path, $encodeContent=true ) {
		$this->memoryStorage->saveItems( array(
			array('ID'=>4, 'first name'=>'Bob','last name'=>'Lindmeier'),
			array('ID'=>5, 'first name'=>'Bob','last name'=>'Saget')
		), $this->schema->getResourceClass('person'), array(
			EarthIT_Storage_ItemSaver::RETURN_SAVED => false,
			EarthIT_Storage_ItemSaver::ON_DUPLICATE_KEY => EarthIT_Storage_ItemSaver::ODK_REPLACE
		));
		
		$bobPatch = array(
			'method' => 'PATCH',
			'path' => '/people/4',
			'contentObject' => array('firstName' => 'Sob')
		);
		if( $encodeContent ) {
			$bobPatch['content'] = json_encode($bobPatch['contentObject']);
			unset($bobPatch['contentObject']);
		}
		
		$requestObject = array(
			'actions' => array(
				'patchBobL' => $bobPatch,
				'getAllBobs' => array(
					'method' => 'GET',
					'path' => '/people',
					'queryString' => 'id=in:4,5'
				),
				'deleteBobS' => array(
					'method' => 'DELETE',
					'path' => '/people/5',
				),
				'getAllBobsAgain' => array(
					'method' => 'GET',
					'path' => '/people',
					'queryString' => 'id=in:4,5'
				),
			)
		);

		$req = $requestParser->parse($method, $path, '', new EarthIT_JSON_PrettyPrintedJSONBlob($requestObject));
		$this->assertTrue( is_array($req), "RequestParser#parse( '$method', '$path', compound action stuff ) should return an array; got ".var_export($req,true) );
		$act = $requestParser->toAction($req);
		$asm = $this->rester->doAction($act, null);
		
		$this->assertEquals( array(
			'actionResults' => array(
				'patchBobL' => array(
					'statusCode' => 200,
					'contentObject' => array(
						'id' => '4',
						'firstName' => 'Sob',
						'lastName' => 'Lindmeier',
					)
				),
				'getAllBobs' => array(
					'statusCode' => 200,
					'contentObject' => array(
						'4' => array(
							'id' => '4',
							'firstName' => 'Sob',
							'lastName' => 'Lindmeier',
						),
						'5' => array(
							'id' => '5',
							'firstName' => 'Bob',
							'lastName' => 'Saget',
						)
					)
				),
				'deleteBobS' => array( 'statusCode' => 204 ),
				'getAllBobsAgain' => array(
					'statusCode' => 200,
					'contentObject' => array(
						'4' => array(
							'id' => '4',
							'firstName' => 'Sob',
							'lastName' => 'Lindmeier',
						)
					)
				)
			)
		), $asm, "Result of compound action should look like I think it should" );
	}
	
	public function testDoCompoundActionWithFancyRequestParser() {
		$requestParser =
			EarthIT_CMIPREST_RequestParser_FancyRequestParser::buildStandardFancyParser(
				$this->schema, $this->schemaObjectNamer);
		$this->_testDoCompoundAction( $requestParser, 'DO-COMPOUND-ACTION', '' );
	}

	public function testDoCompoundActionViaPostWithFancyRequestParser() {
		$requestParser =
			EarthIT_CMIPREST_RequestParser_FancyRequestParser::buildStandardFancyParser(
				$this->schema, $this->schemaObjectNamer);
		$this->_testDoCompoundAction( $requestParser, 'POST', ';compound' );
	}

	public function testDoCompoundActionViaPostWithFancyRequestParserAndUnencodedContentObject() {
		$requestParser =
			EarthIT_CMIPREST_RequestParser_FancyRequestParser::buildStandardFancyParser(
				$this->schema, $this->schemaObjectNamer);
		$this->_testDoCompoundAction( $requestParser, 'POST', ';compound', false );
	}
}
