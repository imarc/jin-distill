<?php

namespace JinDistill\Formatting;

use JinDistill\Analysis\Analyzer;
use JinDistill\Source\SourceId;
use JinDistill\Validation\Validator;

final class Normalizer
{
    private ?Validator $validator;
    private JinRenderer $renderer;

    public function __construct(
        private Analyzer $analyzer,
        ?Validator $validator = null,
        ?JinRenderer $renderer = null,
    ) {
        $this->validator = $validator;
        $this->renderer = $renderer ?? new JinRenderer();
    }

    public function normalize(string $contents, SourceId $source, ?FormatOptions $options = null): NormalizeResult
    {
        $analysis = $this->analyzer->analyze($contents, $source);
        return $this->result($analysis, $source->canonicalPath(), $options);
    }

    public function normalizeFile(string $path, ?FormatOptions $options = null): NormalizeResult
    {
        $analysis = $this->analyzer->analyzeFile($path);
        $canonicalPath = realpath($path);
        if ($canonicalPath === false) {
            throw new \RuntimeException(sprintf('Cannot resolve Jin source: %s', $path));
        }

        return $this->result($analysis, $canonicalPath, $options);
    }

    private function result(\JinDistill\Analysis\AnalysisResult $analysis, string $source, ?FormatOptions $options): NormalizeResult
    {
        $document = $analysis->sourceGraph()->documents()[$source] ?? null;
        if ($document === null) {
            throw new \RuntimeException(sprintf('Analysis did not include Jin source: %s', $source));
        }

        return new NormalizeResult(
            $this->renderer->render($document, $options),
            $analysis,
            ($this->validator ?? new Validator(options: $options))->validate($analysis),
        );
    }
}
