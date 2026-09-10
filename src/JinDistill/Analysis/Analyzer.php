<?php

namespace JinDistill\Analysis;

use JinDistill\Source\SourceId;
use JinDistill\Syntax\Assignment;

final class Analyzer
{
    public function __construct(private SourceGraphBuilder $graphs)
    {
    }

    public function analyze(string $contents, SourceId $source): AnalysisResult
    {
        return $this->result($this->graphs->build($contents, $source));
    }

    public function analyzeFile(string $path): AnalysisResult
    {
        return $this->result($this->graphs->buildFile($path));
    }

    private function result(SourceGraph $graph): AnalysisResult
    {
        $definitions = [];
        foreach (array_reverse($graph->documents()) as $document) {
            foreach ($document->statements() as $statement) {
                if (!$statement instanceof Assignment || str_starts_with($statement->path()->segments()[0], '--')) {
                    continue;
                }
                $key = $statement->path()->toJsonPointer();
                $definitions[$key][] = new Definition($statement->path(), $document->source(), $statement->span());
            }
        }
        $lineages = [];
        foreach ($definitions as $items) {
            $winner = array_pop($items);
            $lineages[] = new Lineage($winner, $items, []);
        }

        return AnalysisResult::sourceOnly($graph, [], new ProvenanceIndex($lineages));
    }
}
