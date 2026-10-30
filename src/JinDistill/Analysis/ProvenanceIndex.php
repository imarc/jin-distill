<?php

namespace JinDistill\Analysis;

use JinDistill\Source\Path;

final class ProvenanceIndex
{
    /** @var array<string, Lineage> */
    private array $lineages = [];

    /** @param list<Lineage> $lineages */
    public function __construct(array $lineages)
    {
        foreach ($lineages as $lineage) {
            $this->lineages[$lineage->winner()->path()->toJsonPointer()] = $lineage;
        }
    }

    /** @return list<Lineage> */
    public function lineages(): array
    {
        return array_values($this->lineages);
    }

    public function lineage(Path $path): ?Lineage
    {
        return $this->lineages[$path->toJsonPointer()] ?? null;
    }
}
