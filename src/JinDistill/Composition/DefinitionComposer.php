<?php

namespace JinDistill\Composition;

use JinDistill\Analysis\AnalysisResult;
use JinDistill\Syntax\Assignment;
use JinDistill\Syntax\Document;

final class DefinitionComposer
{
    public function compose(AnalysisResult $analysis): ComposedDocument
    {
        $statements = [];
        $positions = [];
        $source = null;
        foreach (array_reverse($analysis->sourceGraph()->documents()) as $document) {
            $source ??= $document->source();
            foreach ($document->statements() as $statement) {
                if (!$statement instanceof Assignment || str_starts_with($statement->path()->segments()[0], '--')) {
                    continue;
                }
                $path = $statement->path()->toJsonPointer();
                if (isset($positions[$path])) {
                    $statements[$positions[$path]] = $statement;
                    continue;
                }
                $positions[$path] = count($statements);
                $statements[] = $statement;
            }
        }
        $source ??= new \JinDistill\Source\SourceId('memory://composed.jin', 'composed.jin');
        return new ComposedDocument(new Document($statements, $source), $analysis->provenance());
    }
}
