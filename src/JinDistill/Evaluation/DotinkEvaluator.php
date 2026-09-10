<?php

namespace JinDistill\Evaluation;

use Dotink\Jin\Parser;
use JinDistill\Analysis\AnalysisResult;
use JinDistill\Analysis\Analyzer;

final class DotinkEvaluator
{
    public function __construct(private Analyzer $analyzer)
    {
    }

    public function evaluateFile(string $path): AnalysisResult
    {
        $analysis = $this->analyzer->analyzeFile($path);
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException(sprintf('Cannot read Jin source for evaluation: %s', $path));
        }
        $parser = new Parser();
        $collection = $parser->parse($contents, $path);

        return AnalysisResult::evaluated(
            $analysis->sourceGraph(),
            $analysis->diagnostics(),
            $collection->all(),
            $analysis->provenance(),
        );
    }
}
