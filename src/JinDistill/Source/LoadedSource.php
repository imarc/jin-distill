<?php

namespace JinDistill\Source;

final class LoadedSource
{
    public function __construct(private SourceId $source, private string $contents)
    {
    }

    public function source(): SourceId
    {
        return $this->source;
    }

    public function contents(): string
    {
        return $this->contents;
    }
}
