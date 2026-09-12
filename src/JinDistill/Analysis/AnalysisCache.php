<?php

namespace JinDistill\Analysis;

interface AnalysisCache
{
    public function get(string $key): ?AnalysisResult;

    /** Implementations store source-only analysis; evaluated results are never cached. */
    public function put(string $key, AnalysisResult $result): void;
}
