<?php

namespace JinDistill\Formats;

interface FormatInterface
{
	public function encode(array $data): string;
}