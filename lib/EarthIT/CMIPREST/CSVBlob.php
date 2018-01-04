<?php

class EarthIT_CMIPREST_CSVBlob extends Nife_OutputBlob
{
	protected $csvRows;
	public function __construct($csvRows) {
		$this->csvRows = $csvRows;
		parent::__construct(array($this,'outputCsvRows'));
	}
	
	public function outputCsvRows() {
		$fh = fopen('php://output', 'w');
		foreach( $this->csvRows as $row ) fputcsv($fh, $row);
	}
}
