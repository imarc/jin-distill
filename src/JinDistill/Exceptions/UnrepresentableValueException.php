<?php

namespace JinDistill\Exceptions;

use RuntimeException;

final class UnrepresentableValueException extends RuntimeException
{
    public function __construct(string $path, string $source, mixed $value)
    {
        parent::__construct(sprintf(
            'Cannot render value at %s in %s: %s is not representable in Jin.',
            $path,
            $source,
            get_debug_type($value),
        ));
    }
}
