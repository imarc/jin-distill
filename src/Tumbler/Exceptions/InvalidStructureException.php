<?php

namespace Tumbler\Exceptions;

class InvalidStructureException extends \Exception
{
	public function __construct($message = "Invalid structure", $code = 0, \Throwable $previous = null)
	{
		parent::__construct($message, $code, $previous);
	}
}