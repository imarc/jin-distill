<?php

namespace JinDistill\Source;

final class SourceSpan
{
    public function __construct(
        private SourceId $source,
        private int $startLine,
        private int $startColumn,
        private int $endLine,
        private int $endColumn,
    ) {
    }

    public function toArray(): array
    {
        return [
            'source' => $this->source->canonicalPath(),
            'reference' => $this->source->reference(),
            'start' => ['line' => $this->startLine, 'column' => $this->startColumn],
            'end' => ['line' => $this->endLine, 'column' => $this->endColumn],
        ];
    }
}
