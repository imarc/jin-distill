<?php

namespace JinDistill\Exceptions;

use RuntimeException;

final class AnalysisLimitException extends RuntimeException
{
    public static function exceeded(string $limit, int $configured, int $actual): self
    {
        return new self(sprintf('Jin analysis exceeded the configured %s limit of %d (%d).', $limit, $configured, $actual));
    }
}
