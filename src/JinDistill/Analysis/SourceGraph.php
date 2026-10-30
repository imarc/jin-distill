<?php

namespace JinDistill\Analysis;

use JinDistill\Syntax\Document;

final class SourceGraph
{
    /**
     * @param array<string, Document> $documents
     * @param list<SourceGraphEdge> $edges
     */
    public function __construct(private array $documents, private array $edges)
    {
    }

    /** @return array<string, Document> */
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
