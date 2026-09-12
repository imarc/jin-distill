<?php

namespace JinDistill\Exceptions;

use RuntimeException;

final class InvalidPathException extends RuntimeException
{
    public static function outsideApplicationRoot(string $path, string $root): self
    {
        return new self(sprintf('Jin source %s lies outside the configured application root %s.', $path, $root));
    }
}
