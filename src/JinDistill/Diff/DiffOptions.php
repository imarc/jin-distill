<?php

namespace JinDistill\Diff;

final class DiffOptions
{
    public function __construct(private bool $metadataSensitive = true)
    {
    }

    public function metadataSensitive(): bool { return $this->metadataSensitive; }
}
