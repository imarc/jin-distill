<?php

namespace JinDistill\Syntax;

use JinDistill\Source\Path;
use JinDistill\Source\SourceSpan;

final class Section extends Statement
{
    public function __construct(private Path $path, private string $lexeme, SourceSpan $span)
    {
        parent::__construct($span);
    }

    public function path(): Path
    {
        return $this->path;
    }

    public function lexeme(): string
    {
        return $this->lexeme;
    }
}
