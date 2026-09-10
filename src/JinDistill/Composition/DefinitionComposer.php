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
                if (!$statement instanceof Assignment) {
                    continue;
                }
                if ($statement->path()->segments() === ['--without']) {
                    foreach ((array) $statement->value()->staticValue() as $removedPath) {
                        if (!is_string($removedPath) || $removedPath === '') {
                            continue;
                        }
                        $path = '/' . str_replace('.', '/', $removedPath);
                        if (isset($positions[$path])) {
                            $statements[$positions[$path]] = null;
                            unset($positions[$path]);
                        }
                    }
                    continue;
                }
                if (str_starts_with($statement->path()->segments()[0], '--')) {
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
        return new ComposedDocument(new Document(array_values(array_filter($statements)), $source), $analysis->provenance());
    }
}
