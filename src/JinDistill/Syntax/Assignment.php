<?php

namespace JinDistill\Syntax;

use JinDistill\Source\Path;
use JinDistill\Source\SourceSpan;

final class Assignment extends Statement
{
    /** @param list<Comment> $comments */
    public function __construct(
        private Path $path,
        private Value $value,
        private array $comments,
        private ?Comment $inlineComment,
        SourceSpan $span,
    ) {
        parent::__construct($span);
    }

    public function path(): Path
    {
        return $this->path;
    }

    public function value(): Value
    {
        return $this->value;
    }

    /** @return list<Comment> */
    public function comments(): array
    {
        return $this->comments;
    }

    public function inlineComment(): ?Comment
    {
        return $this->inlineComment;
    }
}
