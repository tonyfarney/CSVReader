<?php
namespace CSVReader;

class CSVReaderException extends \ErrorException {
	// Error constants used as error code (useful for customized error messages)
	public const INDEXING_ERROR = 1;
	public const EMPTY_CSV_ERROR = 2;
	public const INVALID_LINE_ERROR = 3;
	public const UNEXPECTED_HEADER_COLUMN = 4;
	public const FAILED_TO_DETECT_COLUMN_DELIMITER = 5;
	public const FAILED_TO_CREATE_TMP_FILE = 6;
	
	/**
	 * @var array
	 */
	private $_details;
	
	public function __construct(string $message, int $code = null, array $details = null) {
		parent::__construct($message, $code);
		$this->_details = $details;
	}
	
	/**
	 * Details about the error (if any)
	 * @return array|null
	 */
	public function getDetails(): ?array {
		return $this->_details;
	}
}
