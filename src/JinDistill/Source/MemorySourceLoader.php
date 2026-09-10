<?php

namespace JinDistill\Source;

use InvalidArgumentException;

final class MemorySourceLoader implements SourceLoader
{
    /** @var array<string, LoadedSource> */
    private array $sources = [];

    /** @param list<LoadedSource> $sources */
    public function __construct(array $sources)
    {
        foreach ($sources as $source) {
            $this->sources[$source->source()->reference()] = $source;
            $this->sources[$source->source()->canonicalPath()] = $source;
        }
    }

    public function load(string $reference, ?SourceId $from = null): LoadedSource
    {
        if (!isset($this->sources[$reference])) {
            throw new InvalidArgumentException(sprintf('Unknown in-memory Jin source: %s', $reference));
        }

        return $this->sources[$reference];
    }
}
