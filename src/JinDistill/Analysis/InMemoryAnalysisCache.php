<?php

namespace JinDistill\Analysis;

final class InMemoryAnalysisCache implements AnalysisCache
{
    /** @var array<string, AnalysisResult> */
    private array $entries = [];

    public function get(string $key): ?AnalysisResult
    {
        return $this->entries[$key] ?? null;
    }

    public function put(string $key, AnalysisResult $result): void
    {
        if ($result->mode() !== AnalysisMode::SourceOnly) {
            return;
        }

        $this->entries[$key] = $result;
    }

    public function count(): int
    {
        return count($this->entries);
    }
}
