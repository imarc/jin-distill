<?php

namespace JinDistill\Source;

use InvalidArgumentException;

final class FilesystemSourceLoader implements SourceLoader
{
    public function __construct(private PathPolicy $policy)
    {
    }

    public function load(string $reference, ?SourceId $from = null): LoadedSource
    {
        $candidate = $this->candidate($reference, $from);
        $canonicalPath = realpath($candidate);
        if ($canonicalPath === false || !is_readable($canonicalPath)) {
            throw new InvalidArgumentException(sprintf('Cannot read Jin source: %s', $reference));
        }
        $this->policy->assertAllowed($canonicalPath);

        $contents = file_get_contents($canonicalPath);
        if ($contents === false) {
            throw new InvalidArgumentException(sprintf('Cannot read Jin source: %s', $reference));
        }

        return new LoadedSource(new SourceId($canonicalPath, $reference), $contents);
    }

    private function candidate(string $reference, ?SourceId $from): string
    {
        if (str_starts_with($reference, '/')) {
            return $reference;
        }

        return ($from === null ? $this->policy->firstRoot() : dirname($from->canonicalPath())) . '/' . $reference;
    }
}
