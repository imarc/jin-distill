<?php

namespace JinDistill\Analysis;

final class SourceGraph
{
    /** @param array<string, mixed> $documents @param list<SourceGraphEdge> $edges */
    public function __construct(private array $documents, private array $edges)
    {
    }

    /** @return array<string, mixed> */
    public function documents(): array
    {
        return $this->documents;
    }

    /** @return list<SourceGraphEdge> */
    public function edges(): array
    {
        return $this->edges;
    }
}
