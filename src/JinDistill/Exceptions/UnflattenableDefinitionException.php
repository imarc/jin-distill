<?php

namespace JinDistill\Exceptions;

use RuntimeException;

final class UnflattenableDefinitionException extends RuntimeException
{
    /** @param list<object> $conflicts */
    public function __construct(private array $conflicts = [])
    {
        parent::__construct('Jin definitions cannot be flattened without evaluating dynamic composition.');
    }

    /** @return list<object> */
    public function conflicts(): array
    {
        return $this->conflicts;
    }
}
