<?php

namespace JinDistill\Analysis;

use LogicException;

final class AnalysisResult
{
    /** @param list<object> $diagnostics */
    private function __construct(private AnalysisMode $mode, private SourceGraph $sourceGraph, private array $diagnostics, private mixed $resolvedData = null, private ?ProvenanceIndex $provenance = null)
    {
    }

    /** @param list<object> $diagnostics */
    public static function sourceOnly(SourceGraph $sourceGraph, array $diagnostics, ?ProvenanceIndex $provenance = null): self
    {
        return new self(AnalysisMode::SourceOnly, $sourceGraph, $diagnostics, null, $provenance ?? new ProvenanceIndex([]));
    }

    public function mode(): AnalysisMode
    {
        return $this->mode;
    }

    public function sourceGraph(): SourceGraph
    {
        return $this->sourceGraph;
    }

    /** @return list<object> */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    public function resolvedData(): mixed
    {
        if ($this->mode === AnalysisMode::SourceOnly) {
            throw new LogicException('Resolved data is unavailable for source-only analysis.');
        }

        return $this->resolvedData;
    }

    public function provenance(): ProvenanceIndex
    {
        return $this->provenance ?? new ProvenanceIndex([]);
    }
}
