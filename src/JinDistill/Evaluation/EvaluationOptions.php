<?php

namespace JinDistill\Evaluation;

final class EvaluationOptions
{
    /** @param array<string, mixed> $context @param array<string, callable> $functions */
    public function __construct(private array $context = [], private array $functions = [], private bool $associative = true)
    {
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return $this->context;
    }

    /** @return array<string, callable> */
    public function functions(): array
    {
        return $this->functions;
    }

    public function associative(): bool
    {
        return $this->associative;
    }
}
