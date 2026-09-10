<?php

namespace JinDistill\Analysis;

use JinDistill\Source\SourceId;

final class Analyzer
{
    public function __construct(private SourceGraphBuilder $graphs)
    {
    }

    public function analyze(string $contents, SourceId $source): AnalysisResult
    {
        return AnalysisResult::sourceOnly($this->graphs->build($contents, $source), []);
    }

    public function analyzeFile(string $path): AnalysisResult
    {
        return AnalysisResult::sourceOnly($this->graphs->buildFile($path), []);
    }
}
