<?php

namespace Tumbler\Output;

class StdOutput implements OutputInterface
{
	public function write(string $data)
	{
		echo $data;
	}
}