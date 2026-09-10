<?php

namespace JinDistill\Syntax;

use JinDistill\Source\SourceSpan;

final class Comment extends Statement
{
    public function __construct(private string $text, SourceSpan $span)
    {
        parent::__construct($span);
    }

    public function text(): string
    {
        return $this->text;
    }
}
