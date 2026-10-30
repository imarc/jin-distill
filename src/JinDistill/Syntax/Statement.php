<?php

namespace JinDistill\Syntax;

use JinDistill\Source\SourceSpan;

abstract class Statement
{
    public function __construct(private SourceSpan $span)
    {
    }

    public function span(): SourceSpan
    {
        return $this->span;
    }
}
