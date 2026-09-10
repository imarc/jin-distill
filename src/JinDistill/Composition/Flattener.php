<?php

namespace JinDistill\Composition;

use JinDistill\Analysis\AnalysisResult;
use JinDistill\Formatting\FormatOptions;
use JinDistill\Formatting\JinRenderer;

final class Flattener
{
    public function __construct(private ?DefinitionComposer $composer = null, private ?JinRenderer $renderer = null)
    {
        $this->composer ??= new DefinitionComposer();
        $this->renderer ??= new JinRenderer();
    }

    public function flatten(AnalysisResult $analysis, ?FormatOptions $options = null): FlattenResult
    {
        $composed = $this->composer->compose($analysis);
        return new FlattenResult(
            $this->renderer->render($composed->document(), $options),
            $analysis,
            $composed->provenance(),
        );
    }
}
