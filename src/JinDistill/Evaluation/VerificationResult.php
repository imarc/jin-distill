<?php

namespace JinDistill\Evaluation;

final class VerificationResult
{
    /** @param list<string> $differences */
    public function __construct(private array $differences)
    {
    }

    public function isEquivalent(): bool
    {
        return $this->differences === [];
    }

    /** @return list<string> */
    public function differences(): array
    {
        return $this->differences;
    }
}
