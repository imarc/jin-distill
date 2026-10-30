<?php

namespace JinDistill\Composition;

use JinDistill\Analysis\ProvenanceIndex;
use JinDistill\Syntax\Document;

final class ComposedDocument
{
    /** @param list<CompositionConflict> $conflicts */
    public function __construct(private Document $document, private ProvenanceIndex $provenance, private array $conflicts = [])
    {
    }

    public function document(): Document
    {
        return $this->document;
    }
    public function provenance(): ProvenanceIndex
    {
        return $this->provenance;
    }
    /** @return list<CompositionConflict> */
    public function conflicts(): array
    {
        return $this->conflicts;
    }
}
