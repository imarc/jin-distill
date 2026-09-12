<?php

namespace JinDistill\Analysis;

use JinDistill\Source\SourceId;
use JinDistill\Source\Path;
use JinDistill\Syntax\Assignment;

final class Analyzer
{
    public function __construct(
        private SourceGraphBuilder $graphs,
        private ?AnalysisLimits $limits = null,
        private ?AnalysisCache $cache = null,
    ) {
        $this->limits ??= new AnalysisLimits();
    }

    public function analyze(string $contents, SourceId $source): AnalysisResult
    {
        $key = AnalysisCacheKey::for($contents, $source);
        $cached = $this->cache?->get($key);

        if ($cached !== null) {
            return $cached;
        }

        $result = $this->result($this->graphs->build($contents, $source));
        $this->cache?->put($key, $result);

        return $result;
    }

    public function analyzeFile(string $path): AnalysisResult
    {
        return $this->result($this->graphs->buildFile($path));
    }

    private function result(SourceGraph $graph): AnalysisResult
    {
        $this->guard($graph);

        $definitions = [];
        $removals = [];
        foreach (array_reverse($graph->documents()) as $document) {
            foreach ($document->statements() as $statement) {
                if (!$statement instanceof Assignment) {
                    continue;
                }
                if ($statement->path()->segments() === ['--without']) {
                    $paths = $statement->value()->staticValue();
                    foreach (is_array($paths) ? $paths : [$paths] as $path) {
                        if (!is_string($path) || $path === '') {
                            continue;
                        }
                        $removalPath = Path::fromSegments(explode('.', $path));
                        $removals[$removalPath->toJsonPointer()][] = new Definition($removalPath, $document->source(), $statement->span());
                    }
                    continue;
                }
                if (str_starts_with($statement->path()->segments()[0], '--')) {
                    continue;
                }
                $key = $statement->path()->toJsonPointer();
                $definitions[$key][] = new Definition($statement->path(), $document->source(), $statement->span());
            }
        }
        $lineages = [];
        foreach ($definitions as $key => $items) {
            $winner = array_pop($items);
            $lineages[] = new Lineage($winner, $items, $removals[$key] ?? []);
        }

        return AnalysisResult::sourceOnly($graph, [], new ProvenanceIndex($lineages));
    }

    private function guard(SourceGraph $graph): void
    {
        foreach ($graph->documents() as $document) {
            foreach ($document->statements() as $statement) {
                if ($statement instanceof Assignment) {
                    $this->limits->guardSyntaxDepth(count($statement->path()->segments()));
                }
            }
        }
    }
}
