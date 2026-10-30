<?php

namespace JinDistill\Source;

final class SourceId
{
    public function __construct(
        private string $canonicalPath,
        private string $reference,
    ) {
    }

    public function canonicalPath(): string
    {
        return $this->canonicalPath;
    }

    public function reference(): string
    {
        return $this->reference;
    }
}
