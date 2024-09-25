<?php

namespace Tumbler\Formats;

interface FormatInterface
{
	public function encode(array $data): string;
}