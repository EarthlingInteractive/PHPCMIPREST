<?php

class Nife_HTTP_BasicResponse implements Nife_HTTP_Response
{
	public function __construct( $statusCode, $statusText, array $headers, Nife_Blob|EarthIT_JSON_PrettyPrintedJSONBlob $content=null ) {
		$this->statusCode = (int)$statusCode;
		$this->statusText = (string)$statusText;
		$this->headers = $headers;
		$this->content = $content;
	}
	
	public function getStatusCode() {  return $this->statusCode;  }
	public function getStatusText() {  return $this->statusText;  }
	public function getHeaders() {  return $this->headers;  }
	public function getContent() {  return $this->content;  }
}
