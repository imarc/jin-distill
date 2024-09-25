<?php

namespace Tumbler\Output;

class FileOutput implements OutputInterface
{
	protected $file;

	public function __construct($file)
	{
		$this->file = fopen($file, "w");
	}

	public function __destruct()
	{
		fclose($this->file);
	}

	public function write(string $data)
	{
		fwrite($this->file, $data);
	}
}