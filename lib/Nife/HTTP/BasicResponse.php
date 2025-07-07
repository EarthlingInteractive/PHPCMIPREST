<?php

class Nife_HTTP_BasicResponse implements Nife_HTTP_Response
{

	/**
	 * @var int the HTTP status code
	 */
	protected $statusCode;
	/**
	 * @var string the HTTP status text
	 */
	protected $statusText;
	/**
	 * @var array the HTTP headers to be sent with the response
	 * 		* The keys are header names, and the values are the header values.
	 * 		* If a header name appears multiple times, the values will be
	 * 		  concatenated with a comma.
	 */
	protected $headers;
	/**
	 * @var Nife_Blob|EarthIT_JSON_PrettyPrintedJSONBlob|EarthIT_FileTemplateBlob
	 * 		the content of the response, or null if there is no content.
	 * 		* If this is a Nife_Blob, it should be a blob that can be
	 * 		  written to the output stream.
	 * 		* If this is an EarthIT_JSON_PrettyPrintedJSONBlob, it
	 * 		  should be a blob that contains JSON data that can be
	 * 		  written to the output stream.
	 * 		* If this is an EarthIT_FileTemplateBlob, it should be a
	 * 		  blob that contains a file template that can be rendered
	 * 		  with the variables provided in the template.	
	 */
	protected $content;

	public function __construct( int $statusCode, string $statusText, array $headers, Nife_Blob|EarthIT_JSON_PrettyPrintedJSONBlob|EarthIT_FileTemplateBlob|null $content=null ) {
		$this->statusCode = $statusCode;
		$this->statusText = $statusText;
		$this->headers = $headers;
		$this->content = $content;
	}
	
	public function getStatusCode() {  return $this->statusCode;  }
	public function getStatusText() {  return $this->statusText;  }
	public function getHeaders() {  return $this->headers;  }
	public function getContent() {  return $this->content;  }
}
