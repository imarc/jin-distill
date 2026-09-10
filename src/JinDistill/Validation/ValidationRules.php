<?php

namespace JinDistill\Validation;

use JinDistill\Diagnostics\Severity;

final class ValidationRules
{
    /** @param array<string, Severity> $severities */
    private function __construct(private array $severities)
    {
    }

    public static function imarcV1(): self
    {
        return new self(['jin.style.duplicate-path' => Severity::Error]);
    }

    public function severity(string $ruleId): Severity
    {
        return $this->severities[$ruleId] ?? Severity::Warning;
    }

    public function withSeverity(string $ruleId, Severity $severity): self
    {
        return new self([...$this->severities, $ruleId => $severity]);
    }
}
