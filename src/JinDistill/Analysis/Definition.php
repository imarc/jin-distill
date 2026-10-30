<?php

namespace JinDistill\Analysis;

use JinDistill\Source\Path;
use JinDistill\Source\SourceId;
use JinDistill\Source\SourceSpan;

final class Definition
{
    public function __construct(private Path $path, private SourceId $source, private SourceSpan $span)
    {
    }

    public function path(): Path
    {
        return $this->path;
    }

    public function source(): SourceId
    {
        return $this->source;
    }

    public function span(): SourceSpan
    {
        return $this->span;
    }
}
