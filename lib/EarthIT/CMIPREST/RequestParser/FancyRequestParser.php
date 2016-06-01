<?php

/**
 * A Request parser that supports ALL THE APIs
 */
class EarthIT_CMIPREST_RequestParser_FancyRequestParser implements EarthIT_CMIPREST_RequestParser
{
	protected $parsers;
	public function __construct( array $parsers ) {
		$this->parsers = $parsers;
	}
	
	public function addParsers( array $parsers ) {
		foreach( $parsers as $k=>$parser ) {
			$this->parsers[$k] = $parser;
		}
	}
	
	public static function buildStandardFancyParser( EarthIT_Schema $schema, EarthIT_Schema_SchemaObjectNamer $namer, $default='cmip', array $options=array() ) {
		$P = new self(array());
		$P->addParsers( $P->buildStandardParsers($schema, $namer, $default, $options) );
		return $P;
	}
	
	/**
	 * TODO: Lazy-load parsers
	 * @param EarthIT_Schema $schema
	 * @param EarthIT_Schema_SchemaObjectNamer $namer to provide 'REST format' names for things
	 * @param string $default name of default parser to use
	 * @param array $options more options!
	 */
	public function buildStandardParsers(
		EarthIT_Schema $schema, EarthIT_Schema_SchemaObjectNamer $namer, $default='cmip', array $options=array()
	) {
		$cmipParser = new EarthIT_CMIPREST_RequestParser_CMIPRequestParser(
			$schema, $namer,
			new EarthIT_CMIPREST_RequestParser_CMIPResultAssemblerFactory($options));
		$parsers = array(
			'jao' => new EarthIT_CMIPREST_RequestParser_JAORequestParser( $schema, $namer ),
			'cmip' => $cmipParser,
			'compound' => new EarthIT_CMIPREST_RequestParser_CompoundRequestParser($this)
		);
		if( $default !== null ) $parsers['default'] = $parsers[$default];
		return $parsers;
	}
	
	/** Pre-modified path */
	protected static function pmp( array $modifiers, $remainder ) {
		return ($modifiers ? ';'.implode($modifiers) : '').$remainder;
	}
	
	public function parse( $requestMethod, $path, $queryString, Nife_Blob $content=null ) {
		if( preg_match('#^;([^/]*)(.*)#', $path, $bif) ) {
			$apiModifiers = explode(';', $bif[1]);
			$pathRemainder = $bif[2];
		} else if( $requestMethod == 'DO-COMPOUND-ACTION' ) {
			$apiModifiers = array('compound');
			$pathRemainder = $path;
		} else {
			$apiModifiers = array();
			$pathRemainder = $path;
		}
		
		$parserKey = 'default';
		foreach( $apiModifiers as $m ) {
			if( isset($this->parsers[$m]) ) {
				$parserKey = $m;
			}
		}
		
		if( !isset($this->parsers[$parserKey]) ) return null; // No default
		
		$parser = $this->parsers[$parserKey];
		$remainingModifiers = array();
		foreach( $apiModifiers as $m ) if( $m != $parserKey ) $remainingModifiers[] = $m;
		
		$a = $parser->parse($requestMethod, self::pmp($remainingModifiers,$pathRemainder), $queryString, $content);
		if( $a !== null ) return array(
			'parserKey' => $parserKey,
			'data' => $a
		);
		
		return null;
	}

	public function toAction( array $request ) {
		$parser = $this->parsers[$request['parserKey']];
		return $parser->toAction($request['data']);
	}
}
