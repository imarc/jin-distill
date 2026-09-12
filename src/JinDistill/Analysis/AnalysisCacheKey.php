<?php

namespace JinDistill\Analysis;

use JinDistill\Reporting\SchemaVersion;
use JinDistill\Source\SourceId;

final class AnalysisCacheKey
{
    private function __construct()
    {
    }

    /** Immutable content plus identity plus schema version; evaluated data never participates. */
    public static function for(string $contents, SourceId $source): string
    {
        return SchemaVersion::V1 . ':' . hash('sha256', $source->canonicalPath() . "\0" . $contents);
    }
}
