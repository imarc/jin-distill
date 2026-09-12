<?php

namespace JinDistill\Reporting;

final class ReportOptions
{
    public function __construct(private bool $includeValues = false)
    {
    }

    /** Evaluated values stay redacted unless the caller explicitly opts in. */
    public function includeValues(): bool
    {
        return $this->includeValues;
    }

    public function withValues(bool $includeValues): self
    {
        return new self($includeValues);
    }
}
