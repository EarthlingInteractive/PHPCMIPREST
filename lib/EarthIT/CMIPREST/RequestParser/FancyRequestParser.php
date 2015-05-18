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
	
	/** TODO: Lazy-load parsers */
	public static function buildStandardParsers( EarthIT_Schema $schema, callable $nameFormatter, $default='cmip' ) {
		$parsers = array(
			'jao' => new EarthIT_CMIPREST_RequestParser_JAORequestParser( $schema, $nameFormatter ),
			'cmip' => new EarthIT_CMIPREST_RequestParser_CMIPRequestParser(),
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
		} else {
			$apiModifiers = array();
			$pathRemainder = $path;
		}
		foreach( $this->parsers as $key=>$parser ) {
			if( in_array($key, $apiModifiers) or $key == 'default' && !$apiModifiers ) {
				$remainingModifiers = array();
				foreach( $apiModifiers as $m ) if( $m != $key ) $remainingModifiers[] = $m;
				$parser = $this->parsers[$key];
				$a = $parser->parse($requestMethod, self::pmp($remainingModifiers,$pathRemainder), $queryString, $content);
				if( $a !== null ) return array(
					'parserKey' => $key,
					'data' => $a
				);
			}
		}
		return null;
	}

	public function toAction( array $request ) {
		$parser = $this->parsers[$request['parserKey']];
		return $parser->toAction($request['data']);
	}
}
