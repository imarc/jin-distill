<?php

namespace JinDistill\Exceptions;

use JinDistill\Syntax\Document;

final class InvalidStructureException extends \Exception
{
    /** @param list<object> $diagnostics */
    public function __construct(string $message = 'Invalid structure', private array $diagnostics = [], private ?Document $document = null, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /** @return list<object> */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    public function document(): ?Document
    {
        return $this->document;
    }
}
