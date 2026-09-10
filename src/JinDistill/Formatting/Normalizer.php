<?php

namespace JinDistill\Formatting;

use JinDistill\Analysis\Analyzer;
use JinDistill\Source\SourceId;
use JinDistill\Validation\Validator;

final class Normalizer
{
    public function __construct(
        private Analyzer $analyzer,
        private ?Validator $validator = null,
        private ?JinRenderer $renderer = null,
    ) {
        $this->validator ??= new Validator();
        $this->renderer ??= new JinRenderer();
    }

    public function normalize(string $contents, SourceId $source, ?FormatOptions $options = null): NormalizeResult
    {
        $analysis = $this->analyzer->analyze($contents, $source);
        $document = $analysis->sourceGraph()->documents()[$source->canonicalPath()];

        return new NormalizeResult(
            $this->renderer->render($document, $options),
            $analysis,
            $this->validator->validate($analysis),
        );
    }
}
