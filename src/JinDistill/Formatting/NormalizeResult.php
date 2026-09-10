<?php

namespace JinDistill\Formatting;

use JinDistill\Analysis\AnalysisResult;

final class NormalizeResult
{
    /** @param list<object> $diagnostics */
    public function __construct(private string $content, private AnalysisResult $analysis, private array $diagnostics)
    {
    }

    public function content(): string { return $this->content; }
    public function analysis(): AnalysisResult { return $this->analysis; }
    /** @return list<object> */
    public function diagnostics(): array { return $this->diagnostics; }
}
