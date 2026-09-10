<?php

namespace JinDistill\Analysis;

use JinDistill\Source\SourceId;

final class SourceGraphEdge
{
    public function __construct(private SourceId $child, private SourceId $parent, private string $reference)
    {
    }

    public function child(): SourceId
    {
        return $this->child;
    }

    public function parent(): SourceId
    {
        return $this->parent;
    }

    public function reference(): string
    {
        return $this->reference;
    }
}
