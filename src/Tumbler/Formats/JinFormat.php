<?php

namespace Tumbler\Formats;

use Tumbler\Exceptions\InvalidStructureException;

class JinFormat implements FormatInterface
{
	/**
	 * Holds the data that will be the final output.
	 * @var string
	 */
	protected $data = "";


	/**
	 * @param int    $boundary   The depth to switch from INI to JSON format.
	 * @param string $tabs       The tab character to use for indentation.
	 * @param bool   $extensions Whether to include the --extends key in the output.
	 * @param bool   $strict   Whether to gracefully handle invalid structures or throw an exception.
	 */
	public function __construct(
		protected int    $boundary   = 1, 
		protected string $tabs       = "\t", 
		protected bool   $extensions = true,
		protected bool   $strict   = true
	) {
		$this->boundary = $boundary;
		$this->tabs = $tabs;
		$this->extensions = $extensions;
	}


	/**
	 * 
	 */
	public function encode($array): string
	{
        if (isset($array["--extends"])) {
			if ($this->extensions !== false) {
				$extends = sprintf('file(%s)', $array["--extends"]);
				$this->write(
					sprintf("%s %s\n", $this->encodeKey('--extends', 0, 'ini'), $this->encodeValue($extends, 0))
				);
			}
            unset($array["--extends"]);
        }

		$array = $this->prepareInput($array);
		foreach($array as $key => $value) {
			$this->handle($key, $value);
		}

		return $this->data;
	}


	/**
	 * 
	 */
	protected function prepareInput($array) 
	{
		$marked = [];
		foreach ($array as $key => $value) {
			if ($this->findArrayDepthIssues($value)) {
				$marked[$key] = true;	
			}
		}
		foreach ($marked as $key => $value) {
			$saved = $array[$key];
			unset($array[$key]);
			$array = array_merge([$key => $saved], $array);
		}
		return $array;
	}


	/**
	 * 
	 */
	protected function findArrayDepthIssues($value, $depth = 0) {
		if ($depth > $this->boundary) {
			return false;
		}
		if (!is_array($value)) {
			return false;
		}
		if (is_array($value) && array_is_list($value)) {
			if ($this->strict) {
				throw new InvalidStructureException("The config structure is not able to be represented given the current INI boundary depth.");
			}
			return true;
		}

		foreach ($value as $k => $v) {
			if ($this->findArrayDepthIssues($v, $depth + 1)) {
				return true;
			}
		}
		return false;
	}


	/**
	 * 
	 */
	protected function handle($key, $value, $depth = 0, $context = 'ini')
	{
		$this->writeTabs($depth);

		if ($context === 'ini') {
			if ($depth >= $this->boundary) {
				$context = 'object';
			}
		}

		switch ($this->valueType($value)) {
			case 'primitive':
				switch ($context) {
					case 'array':
						$this->write(
							sprintf("%s,\n", $this->encodeValue($value, $depth))
						);
						break;
					default:
						$this->write(
							sprintf("%s %s\n", $this->encodeKey($key, $depth, $context), $this->encodeValue($value, $depth))
						);
						break;
				}
				break;
			case 'array':
				switch ($context) {
					case 'array':
						$this->write("[\n");
						foreach ($value as $k => $v) {
							$this->handle($k, $v, $depth + 1, 'array');
						}
						$this->writeTabs($depth);
						$this->write("],\n\n");
						break;
					default:
						$this->write(
							sprintf("%s [\n", $this->encodeKey($key, $depth, $context))
						);
						foreach ($value as $k => $v) {
							$this->handle($k, $v, $depth + 1, 'array');
						}
						$this->writeTabs($depth);
						if ($context == 'object') {
							$this->write("],\n\n");
						} else {
							$this->write("]\n\n");
						}
						break;
				}
				break;
			case 'object':
				switch ($context) {
					case 'array':
						$this->write("{\n");
						foreach ($value as $k => $v) {
							$this->handle($k, $v, $depth + 1, 'object');
						}
						$this->writeTabs($depth);
						$this->write("},\n\n");
						break;
					case 'object':
						$this->write(
							sprintf("%s {\n", $this->encodeKey($key, $depth, 'object'))
						);
						foreach ($value as $k => $v) {
							$this->handle($k, $v, $depth + 1, 'object');
						}
						$this->writeTabs($depth);
						if ($depth > $this->boundary) {
							$this->write("},\n\n");
						} else {
							$this->write("}\n\n");
						}
						break;
					default:
						$this->write(
							sprintf("[%s]\n\n", $key)
						);
						foreach ($value as $k => $v) {
							$this->handle($k, $v, $depth + 1);
						}
						$this->write("\n");
						break;
				}
				break;
			default:
				throw new InvalidStructureException("Unexpected, unknown, or invalid structure detected.");
				break;
		}
	}


	/**
	 * 
	 */
    protected function valueType($value)
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                return 'array';
            } else {
                return 'object';
            }
        } else {
            return 'primitive';
        }
    }


	/**
	 * 
	 */
	protected function encodeKey($key, $depth, $context) 
	{
		if ($context !== 'ini' && $depth > $this->boundary) {
			return sprintf('"%s": ', $key);
		} else {
			return sprintf('%s =', $key);
		}
	}


    /**
     * 
     */
    protected function encodeValue($value, $depth)
    {
		$comma = ',';
		if ($depth <= $this->boundary) {
			$comma = '';
		}

        if ($value === null) {
            return sprintf('%s%s', 'null' , $comma);
        }

        if (is_bool($value)) {
            return sprintf('%s%s', ($value ? 'true' : 'false'), $comma);
        }

		if (is_numeric($value)) {
			return sprintf('%d%s', $value, $comma);
		}

		if ($depth > $this->boundary) { 
			return sprintf('"%s"%s', $value, $comma);
		} else {
			return sprintf("%s%s\n", $value, $comma);
		}
    }


	/**
	 * 
	 */
	protected function writeTabs($depth)
	{
		for ($i = 0; $i < $depth; $i++) {
			$this->write($this->tabs);
		}
	}	


	/**
	 * 
	 */
	protected function write($string)
	{
		$this->data .= $string;
	}
}