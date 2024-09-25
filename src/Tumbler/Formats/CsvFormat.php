<?php

namespace Tumbler\Formats;

class CsvFormat implements FormatInterface
{
	/**
	 * The data to be output
	 * @var string
	 */
	protected string $data;


	/**
	 * @param string $delimiter  The delimiter to use between fields
	 * @param array  $header     The header row to output, leave empty for no header
	 * @param bool   $extensions Whether to output the --extends field
	 * @param array  $excludes   Fields (and children) to exclude from the output
	 * @param array  $cloak      Strings within field names to "hide" from the output
	 * @param array  $mappings   Map config values to new values
	 */
	public function __construct(
		protected string $delimiter  = ",", 
		protected array  $header     = ['Field', 'Default Value'], 
		protected bool   $extensions = true, 
		protected array  $excludes   = [], 
		protected array  $cloak      = [],
		protected array  $mappings   = [],
	) {
		$this->data = '';
	}


	/**
	 * 
	 */
	public function encode(array $data): string
	{
		if (!empty($this->header)) {
			$this->outputHeader($this->header);
		}

		if (isset($data["--extends"])) {
			if ($this->extensions) {
				$this->write(
					['Extends', $data["--extends"]]
				);
			}
			unset($data["--extends"]);
		}

		foreach($data as $key => $value) {
			$this->handle($key, $value);
		}

		return $this->data;
	}


	/**
	 * 
	 */
	public function handle($key, $value)
	{
		if (in_array($key, $this->excludes)) {
			return;
		}
		switch ($this->valueType($value)) {
			case 'primitive':
				$key = trim(str_replace($this->cloak, '', $key), '.');
				$this->write(
					[$key, $this->encodeValue($value)]
				);
				break;
			default:
				foreach ($value as $k => $v) {
					$compositeKey = sprintf('%s.%s', $key, $k);
					$this->handle($compositeKey, $v);
				}
				break;
		}
	}


	/**
	 * 
	 */
	protected function valueType($value) 
	{
		if (is_array($value)) {
			return 'array';
		}
		if (is_object($value)) {
			return 'object';
		}
		return 'primitive';
	}


	/**
	 * Encode Values
	 */
	protected function encodeValue($value)
	{
		$value = json_encode($value);
		if (isset($this->mappings[$value])) {
			return $this->mappings[$value];
		}
		return $value;
	}


	/**
	 * 
	 */
	protected function outputHeader($array)
	{
		$this->write(
			$this->header
		);
	}


	/**
	 * 
	 */
	protected function write(array $data)
	{
		$fp = fopen('php://temp', 'r+');
		fputcsv($fp, $data, $this->delimiter, '"');
		rewind($fp);
		$data = fread($fp, 1048576);
		fclose($fp);
		$this->data .= rtrim($data) . "\n";
	}
}