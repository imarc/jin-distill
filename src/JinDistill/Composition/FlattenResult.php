<?php

namespace JinDistill\Composition;

use JinDistill\Analysis\AnalysisResult;
use JinDistill\Analysis\ProvenanceIndex;

final class FlattenResult
{
    public function __construct(private string $content, private AnalysisResult $analysis, private ProvenanceIndex $provenance)
    {
    }

    public function content(): string { return $this->content; }
    public function analysis(): AnalysisResult { return $this->analysis; }
    public function provenance(): ProvenanceIndex { return $this->provenance; }
    /** @return list<object> */
    public function diagnostics(): array { return $this->analysis->diagnostics(); }
}
