<?php

namespace JinDistill\Evaluation;

use JinDistill\Analysis\AnalysisResult;
use JinDistill\Analysis\Analyzer;

final class DotinkEvaluator
{
    public function __construct(private Analyzer $analyzer, private ?JinEvaluator $evaluator = null)
    {
    }

    public function evaluateFile(string $path, ?EvaluationOptions $options = null): AnalysisResult
    {
        $analysis = $this->analyzer->analyzeFile($path);
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException(sprintf('Cannot read Jin source for evaluation: %s', $path));
        }
        $evaluator = $options === null
            ? ($this->evaluator ?? JinEvaluator::fromOptions())
            : JinEvaluator::fromOptions($options);

        return AnalysisResult::evaluated(
            $analysis->sourceGraph(),
            $analysis->diagnostics(),
            $evaluator->evaluate($contents, $path),
            $analysis->provenance(),
        );
    }
}
