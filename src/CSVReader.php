<?php
namespace CSVReader;

class CSVReader {
	/**
	 * Loaded lines from CSV content
	 * @var string[]
	 */
	protected $_lines = [];
	/**
	 * Column delimiter
	 * @var string
	 */
	private $_columnDelimiter = '';
	/**
	 * Enclosure
	 */
	private $_enclosure = '';
	/**
	 * Escape character
	 */
	private $_escape = '';
	/**
	 * In cases the CSV does not have a header line, can be useful to set a
	 * name (index) for each column in the columns array
	 * @var string[]
	 */
	private $_indexing = [];
	/**
	 * In cases the CSV has a header in it's first line, this array will set
	 * wich column names are expected.
	 * @var string[]
	 */
	private $_header = [];
	/**
	 * Determines whether to compare the column names read in the file header
	 * with the expected ones will trim
	 * @var bool
	 */
	private $_trimHeaderColumnNamesToCompare = false;
	/**
	 * Determines whether to compare the column names read in the
	 * file header with the expected ones will make a comparison disregarding uppercase letters
	 * @var bool
	 */
	private $_caseInsensitiveHeaderColumnNamesToCompare = false;
	/**
	 * Aliased header. Only seted if header and indexing are set
	 * @var array
	 */
	private $_aliasedHeader = [];
	/**
	 * Array containing the header columns in the same order they was read
	 * from the CSV
	 * Only columns informed in the header line will be in this array
	 * @var string[]
	 */
	protected $_headerRead = [];
	/**
	 * Amount of lines read from the CSV (regardless whether they have content or not)
	 * @var integer
	 */
	private $_amountReadLines = 0;
	/**
	 * Amount of processed lines, that is, lines with content, including the header line
	 * @var integer
	 */
	private $_amountProcessedLines = 0;
	/**
	 * Array containing the lines's number containing number of values
	 * incompatible with the number of columns in the header
	 * Números das linhas no arquivo que possuem número de valores incompatíveis
	 * com o número de colunas definidas no cabeçalho ou na indexação
	 * @var array
	 */
	private $_linesInvalidSize = [];
	/**
	 * Encoding used for the CSV content and strings in this class. Preferrably UTF-8
	 * @var string
	 */
	private $_encoding = 'UTF-8';

	/**
	 * Sets the possible names of the header columns and use them as the lines array index
	 * @param array $header Array containing the header columns names
	 * Obs: the columns can shows up in any order in the CSV
	 * @throws CSVReaderException
	 * @param array $header
	 * @param bool $trim Determines whether to compare the column names read in the file header
	 *   with the expected ones will trim
	 * @param bool $caseInsensitive Determines whether to compare the column names read in the
	 *   file header with the expected ones will make a comparison disregarding uppercase letters
	 * @return self
	 */
	public function setHeader(array $header, bool $trim = false, bool $caseInsensitive = false): self {
		if (count(array_unique($header)) != count($header)) {
			throw new CSVReaderException(
				'Não é permitido colunas de mesmo nome no cabeçalho do arquivo.',
				CSVReaderException::INDEXING_ERROR
			);
		}
		$this->_header = $header;
		$this->_trimHeaderColumnNamesToCompare = $trim;
		$this->_caseInsensitiveHeaderColumnNamesToCompare = $caseInsensitive;
		return $this;
	}
	/**
	 * Returns the header columns names set
	 * @return string[]
	 */
	public function getHeader(): array {
		return $this->_header;
	}
	
	/**
	 * Returns the amount of lines actualy read from CSV
	 * @return int
	 */
	public function getAmountReadLines(): int {
		return $this->_amountReadLines;
	}
	
	/**
	 * Returns the amount of lines processed from CSV (including the header line)
	 * @return int
	 */
	public function getAmountProcessedLines(): int {
		return $this->_amountProcessedLines;
	}
	
	/**
	 * Returns the loaded lines from CSV
	 * @return string[]
	 */
	public function getLines(): array {
		return $this->_lines;
	}
	
	/**
	 * Sets the names of the columns read from CSV in the read lines array
	 * @param array $indexing Array with the columns names, in the same order they will be found in CSV.
	 *   Obs: If header is set, this array should have the corresponding header column name in its index
	 *   and the columns order doesn't matter. It will work a "rename" for the header columns names
	 * @throws CSVReaderException
	 * @return self
	 */
	public function setIndexing(array $indexing): self {
		if (count(array_unique($indexing)) != count($indexing)) {
			throw new CSVReaderException(
				'Não é permitido colunas de mesmo nome para indexação do arquivo.',
				CSVReaderException::INDEXING_ERROR
			);
		}
		$this->_indexing = $indexing;
		return $this;
	}
	/**
	 * Returns the columns names set
	 * @return string[]
	 */
	public function getIndexing(): array {
		return $this->_indexing;
	}
	
	/**
	 * Returns the original field name by the index configured
	 * @param unknown $index Index configured
	 * @return mixed|null Returns null in case of no index configured for the field
	 */
	public function getOriginalFieldNameByIndex($index) {
		return $this->_aliasedHeader[$index] ?? $this->_indexing[$index] ?? null;
	}
	
	/**
	 * Returns the original field names by the index configured
	 * @param array $indexes Array of configured indexes names
	 * @return array
	 */
	public function getOriginalFieldNamesByIndex(array $indexes): array {
		$ret = [];
		foreach ($indexes as $index) {
			$ret[] = $this->getOriginalFieldNameByIndex($index);
		}
		return $ret;
	}
	
	/**
	 * Loads the CSV content
	 * @param string $csvContent CSV content to be loaded
	 * @param string $columnDelimiter The column delimiter. If empty and not previously set,
	 *   will be automatically detected
	 * @param string $enclosure The enclose character
	 * @param string $escape The escape character
	 * @return string[] Processed lines
	 * @throws CSVReaderException In case of any error
	 */
	public function load(
		$csvContent, $columnDelimiter = null, string $enclosure = '"', string $escape = '\\'
	): array {
		// Vars reset
		$this->_preLoadReset();
		$this->setEnclosure($enclosure);
		$this->setEscape($escape);
		if ($columnDelimiter) {
			$this->setColumnDelimiter($columnDelimiter);
		} elseif (!$this->getColumnDelimiter()) {
			$this->_detectColumnDelimiter($csvContent);
		}
		
		// Checks the content, sets the delimiters by processing the first non empty line
		$lines = $this->_loadCSVLines($csvContent);
		$hasContent = false;
		$header = [];
		foreach ($lines as $i => $columns) {
			$this->_amountReadLines++;
			if ($this->_isEmptyLine($columns)) { // Just ignore empty lines
				unset($lines[$i]);
				continue;
			}
			$hasContent = true;
			
			if ($this->getHeader()) {
				$this->processHeader($columns);
				$header = $this->_headerRead;
				unset($lines[$i]); // Line already processed
				$this->_amountProcessedLines++;
			}
			break;
		}
		if (!$hasContent) {
			throw new CSVReaderException('Conteúdo CSV vazio.', CSVReaderException::EMPTY_CSV_ERROR);
		}
		
		// Checks the use of indexing
		$indexes = $this->getIndexing();
		if ($indexes) {
			if (!$header) { // Assumes the configured indexing
				$header = $indexes;
			} else { // Aliases the header columns names
				foreach ($header as $hi => $hn) {
					if (isset($indexes[$hn])) {
						$header[$hi] = $indexes[$hn];
					}
				}
				$this->_aliasedHeader = array_flip($indexes);
			}
		}
		$headerSize = count($header);
		
		// Process each CSV line
		foreach ($lines as $i => $columns) {
			$this->_amountReadLines++;
			if ($this->_isEmptyLine($columns)) { // Just ignore empty lines
				unset($lines[$i]);
				continue;
			}
			if ($header) { // Associative array
				if ($headerSize != count($columns)) {
					$this->_linesInvalidSize[] = $this->_amountReadLines;
					continue;
				}
				$assocColumns = [];
				foreach ($columns as $j => $column) {
					$assocColumns[$header[$j]] = $columns[$j]; // Creates the associative index
				}
				$this->_lines[] = $assocColumns;
			} else {
				$this->_lines[] = $columns;
			}
			unset($lines[$i]); // Free the memory
			$this->_amountProcessedLines++;
		} // End foreach lines
		if ($this->_linesInvalidSize) {
			throw new CSVReaderException(
				'As seguintes linhas do arquivo possuem número incompatível de valores: '.implode(', ', $this->_linesInvalidSize),
				CSVReaderException::INVALID_LINE_ERROR
			);
		}
		return $this->_lines;
	}
	
	/**
	 * Sets the encoding used for the CSV content and strings in this class. Default is UTF-8
	 * @param string $encoding
	 */
	public function setEncoding(string $encoding) {
		$this->_encoding = $encoding;
	}
	
	/**
	 * Processes and validates the header
	 * @param array $columnsRead Columns read from the header
	 * @throws CSVReaderException
	 */
	protected function processHeader(array $columnsRead) {
		$this->_headerRead = [];
		$invalidColumns = [];
		foreach ($columnsRead as $column) {
			$headerColumn = $this->_getArrayHeader($column);
			if ($headerColumn === null) { // Checks if this column is expected
				$invalidColumns[] = $column;
			} else {
				$this->_headerRead[] = $headerColumn;
			}
		}
		if ($invalidColumns) {
			throw new CSVReaderException(
				'As seguintes colunas não são esperadas no cabeçalho: '.implode(", ", $invalidColumns),
				CSVReaderException::UNEXPECTED_HEADER_COLUMN
			);
		}
		if (count(array_unique($this->_headerRead)) !== count($this->_headerRead)) {
			throw new CSVReaderException(
				'O CSV contém colunas repetidas.',
				CSVReaderException::UNEXPECTED_HEADER_COLUMN
			);
		}
	}
	
	/**
	 * Resets all the delimiters, enclosure and escape
	 */
	public function resetDelimiters() {
		$this->setColumnDelimiter('');
		$this->setEnclosure('');
		$this->setEscape(null);
	}
	/**
	 * Sets the column delimiter (generally "," or ";" character)
	 * @param string $delimitador
	 * @return self
	 */
	public function setColumnDelimiter(string $delimiter): self {
		$this->_columnDelimiter = $delimiter;
		return $this;
	}
	
	/**
	 * Returns the column delimiter
	 * @return string
	 */
	public function getColumnDelimiter(): string {
		return $this->_columnDelimiter;
	}
	
	/**
	 * Sets the enclosure (generally the " character)
	 * @param string $enclosure
	 * @return self
	 */
	public function setEnclosure(string $enclosure): self {
		$this->_enclosure = $enclosure;
		return $this;
	}
	
	/**
	 * Returns the enclosure character
	 * @return string
	 */
	public function getEnclosure() {
		return $this->_enclosure;
	}
	
	/**
	 * Sets the escape (generally the \ character)
	 * @param string $escape
	 * @return self
	 */
	public function setEscape(string $escape): self {
		$this->_escape = $escape;
		return $this;
	}
	
	/**
	 * Returns the escape character
	 * @return string
	 */
	public function getEscape() {
		return $this->_escape;
	}
	
	/**
	 * Try to detect the column delimiter by analising the CSV
	 * @param string $csv
	 * @throws CSVReaderException
	 */
	private function _detectColumnDelimiter(string $csv): void {
		// Most common delimiters
		$commonDelimiters = [
			";" => 0,
			"," => 0,
			"|" => 0,
			"\t" => 0,
		];
		$token = "\n";
		$line = strtok($csv, $token);
		while (trim($line) === '') {
			$line = strtok($token);
		}
		// Count ocurrencies for each delimiter
		foreach ($commonDelimiters as $delimiter => &$ocurs) {
			$ocurs = substr_count($line, $delimiter);
		}
	
		// Order to get the delimiter with more ocurrencies
		arsort($commonDelimiters, SORT_NUMERIC);
		if (current($commonDelimiters) === 0) {
			throw new CSVReaderException(
				'Não foi possível identificar o delimitador de colunas.',
				CSVReaderException::FAILED_TO_DETECT_COLUMN_DELIMITER
			);
		}
		$this->setColumnDelimiter(key($commonDelimiters));
	}
	
	/**
	 * Resets all internal variables
	 * @return self
	 */
	public function reset(): self {
		$this->_lines = $this->_indexing = $this->_header = $this->_headerRead
			= $this->_linesInvalidSize = $this->_aliasedHeader = [];
		$this->_columnDelimiter = $this->_enclosure = $this->_escape = '';
		$this->_amountReadLines = $this->_amountProcessedLines = 0;
		return $this;
	}
	
	/**
	 * Vars reset before loading a new CSV content
	 */
	private function _preLoadReset() {
		$this->_amountProcessedLines = $this->_amountReadLines = 0;
		$this->_linesInvalidSize = $this->_lines = [];
	}
	
	/**
	 * Creates a temporary file and puts a string into it
	 * @param string $s
	 * @return resource|null Returns null in case of error
	 */
	private function _createTmpFile(string $s) {
		$rs = tmpfile();
		if ($rs) {
			if (fwrite($rs, $s) === false) {
				fclose($rs);
				return null;
			}
			if (!rewind($rs)) {
				fclose($rs);
				return null;
			}
			return $rs;
		}
		return null;
	}
	
	/**
	 * Carrega as linhas do arquivo CSV
	 * @param string $csv
	 * @throws CSVReaderException
	 * @return array
	 * @throws CSVReaderException
	 */
	private function _loadCSVLines(string $csv): array {
		$rs = $this->_createTmpFile($csv);
		if (!$rs) {
			throw new CSVReaderException(
				'Falou ao criar o arquivo temporário para processamento do CSV.',
				CSVReaderException::FAILED_TO_CREATE_TMP_FILE
			);
		}
		$lines = [];
		while ($line = fgetcsv($rs, null, $this->_columnDelimiter, $this->_enclosure, $this->_escape)) {
			$lines[] = $line;
		}
		return $lines;
	}
	
	/**
	 * Adjusts the column name to compare
	 * @param string $column
	 * @return string
	 */
	private function _adjustHeaderColumnToCompare(string $column): string {
		if ($this->_trimHeaderColumnNamesToCompare) {
			$column = trim($column);
		}
		if ($this->_caseInsensitiveHeaderColumnNamesToCompare) {
			$column = mb_strtoupper($column, $this->_encoding);
		}
		return $column;
	}
	
	/**
	 * Checks wheter the column is into the expected columns or not and returns
	 * the expected column as it is expected. Returns null if not expected
	 * @param string $column
	 * @return string
	 */
	private function _getArrayHeader(string $column): ?string {
		$headerColumns = $this->getHeader();
		$column = $this->_adjustHeaderColumnToCompare($column);
		foreach ($headerColumns as $headerColumn) {
			if ($column === $this->_adjustHeaderColumnToCompare($headerColumn)) {
				return $headerColumn;
			}
		}
		return null;
	}
	
	
	/**
	 * Checks whether the line is empty or not
	 * @param array $columns
	 * @return boolean
	 */
	private function _isEmptyLine(array $columns): bool {
		foreach ($columns as $column) {
			if (trim($column) !== '') {
				return false;
			}
		}
		return true;
	}
}
