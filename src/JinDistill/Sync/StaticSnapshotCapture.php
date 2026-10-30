<?php

namespace JinDistill\Sync;

use InvalidArgumentException;
use JinDistill\Analysis\AnalysisResult;
use JinDistill\Composition\DefinitionComposer;
use JinDistill\Syntax\Assignment;

final class StaticSnapshotCapture
{
    /** @return array{StaticNode, array<string, array{source: string, line: int}>}
     */
    public function capture(AnalysisResult $analysis, string $locationRoot): array
    {
        $this->validateDirectives($analysis);
        $composed = (new DefinitionComposer())->compose($analysis)->document();
        $tree = StaticNode::map();
        $locations = [];
        foreach ($composed->statements() as $statement) {
            if (!$statement instanceof Assignment) {
                continue;
            }
            $node = StaticNode::capture(
                $statement->value()->isStaticallyKnown() ? $statement->value()->structuredValue() : $statement->value()->raw(),
                !$statement->value()->isStaticallyKnown(),
            );
            $segments = $statement->path()->segments();
            $tree = $tree->withPath($segments, $node);
            $lineage = $analysis->provenance()->lineage($statement->path());
            if ($lineage === null || $lineage->overridden() !== []) {
                continue;
            }
            $source = $statement->span()->source()->canonicalPath();
            if (str_starts_with($source, rtrim($locationRoot, '/') . '/')) {
                $locations[$statement->path()->toJsonPointer()] = [
                    'source' => substr($source, strlen(rtrim($locationRoot, '/')) + 1),
                    'line' => $statement->span()->startLine(),
                ];
            }
        }
        return [$tree, $locations];
    }

    private function validateDirectives(AnalysisResult $analysis): void
    {
        $edgeCounts = [];
        foreach ($analysis->sourceGraph()->edges() as $edge) {
            $source = $edge->child()->canonicalPath();
            $edgeCounts[$source] = ($edgeCounts[$source] ?? 0) + 1;
        }
        foreach ($analysis->sourceGraph()->documents() as $document) {
            $extends = 0;
            foreach ($document->statements() as $statement) {
                if (!$statement instanceof Assignment) {
                    continue;
                }
                $path = $statement->path()->segments();
                if ($path === ['--extends']) {
                    $extends++;
                }
                if ($path === ['--without']) {
                    $value = $statement->value()->staticValue();
                    $paths = is_array($value) ? $value : [$value];
                    if (!$statement->value()->isStaticallyKnown() || $paths === []) {
                        throw new InvalidArgumentException('Static snapshot requires a static --without path.');
                    }
                    foreach ($paths as $removed) {
                        if (!is_string($removed) || $removed === '') {
                            throw new InvalidArgumentException('Static snapshot requires a static --without path.');
                        }
                    }
                }
            }
            if ($extends !== ($edgeCounts[$document->source()->canonicalPath()] ?? 0)) {
                throw new InvalidArgumentException('Static snapshot cannot resolve every --extends directive.');
            }
        }
    }

}
