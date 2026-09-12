<?php

namespace JinDistill\Formats;

interface FormatInterface
{
    /** @param array<array-key, mixed> $data */
    public function encode(array $data): string;
}
