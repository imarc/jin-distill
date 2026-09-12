<?php

namespace JinDistill\Composition;

use JinDistill\Analysis\AnalysisResult;
use JinDistill\Formatting\FormatOptions;
use JinDistill\Formatting\JinRenderer;

final class Flattener
{
    private DefinitionComposer $composer;
    private JinRenderer $renderer;

    public function __construct(?DefinitionComposer $composer = null, ?JinRenderer $renderer = null)
    {
        $this->composer = $composer ?? new DefinitionComposer();
        $this->renderer = $renderer ?? new JinRenderer();
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
