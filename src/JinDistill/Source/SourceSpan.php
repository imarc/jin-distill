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

    public function source(): SourceId
    {
        return $this->source;
    }
    public function startLine(): int
    {
        return $this->startLine;
    }
    public function startColumn(): int
    {
        return $this->startColumn;
    }
    public function endLine(): int
    {
        return $this->endLine;
    }
    public function endColumn(): int
    {
        return $this->endColumn;
    }

    /** @return array{source: string, reference: string, start: array{line: int, column: int}, end: array{line: int, column: int}} */
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
