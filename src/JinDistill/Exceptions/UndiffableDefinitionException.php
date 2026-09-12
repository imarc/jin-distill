<?php

namespace JinDistill\Exceptions;

use RuntimeException;

final class UndiffableDefinitionException extends RuntimeException
{
    /** @param list<object> $conflicts */
    public function __construct(private array $conflicts = [])
    {
        parent::__construct('Jin definitions cannot be diffed without evaluating dynamic composition.');
    }

    /** @return list<object> */
    public function conflicts(): array
    {
        return $this->conflicts;
    }
}
