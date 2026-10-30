<?php

namespace JinDistill\Analysis;

use JinDistill\Decoders\JinDecoder;
use JinDistill\Exceptions\CircularInheritanceException;
use JinDistill\Source\ExtendsResolver;
use JinDistill\Source\SourceId;
use JinDistill\Source\SourceLoader;
use JinDistill\Syntax\Assignment;
use JinDistill\Syntax\Document;

final class SourceGraphBuilder
{
    /** @var array<string, Document> */
    private array $documents = [];
    /** @var array<string, true> */
    private array $visiting = [];
    /** @var list<SourceGraphEdge> */
    private array $edges = [];

    private AnalysisLimits $limits;

    /** @param list<ExtendsResolver> $resolvers */
    public function __construct(
        private SourceLoader $loader,
        private JinDecoder $decoder,
        private array $resolvers,
        ?AnalysisLimits $limits = null,
    ) {
        $this->limits = $limits ?? new AnalysisLimits();
    }

    public function build(string $contents, SourceId $source): SourceGraph
    {
        $this->documents = [];
        $this->visiting = [];
        $this->edges = [];
        $this->limits->guardBytes(strlen($contents));
        $this->visit($this->decoder->decode($contents, $source), 0);

        return new SourceGraph($this->documents, $this->edges);
    }

    public function buildFile(string $path): SourceGraph
    {
        $loaded = $this->loader->load($path);

        return $this->build($loaded->contents(), $loaded->source());
    }

    private function visit(Document $document, int $depth): void
    {
        $this->limits->guardInheritanceDepth($depth);
        $source = $document->source();
        if (isset($this->visiting[$source->canonicalPath()])) {
            throw new CircularInheritanceException(sprintf('Circular Jin inheritance detected at %s.', $source->canonicalPath()));
        }
        if (isset($this->documents[$source->canonicalPath()])) {
            return;
        }
        $this->visiting[$source->canonicalPath()] = true;
        $this->documents[$source->canonicalPath()] = $document;
        foreach ($document->statements() as $statement) {
            if (!$statement instanceof Assignment || $statement->path()->segments() !== ['--extends']) {
                continue;
            }
            foreach ($this->resolvers as $resolver) {
                if (!$resolver->supports($statement->value())) {
                    continue;
                }
                $reference = $resolver->resolve($statement->value(), $source);
                $loaded = $this->loader->load($reference, $source);
                $this->limits->guardBytes(strlen($loaded->contents()));
                $parent = $this->decoder->decode($loaded->contents(), $loaded->source());
                $this->edges[] = new SourceGraphEdge($source, $parent->source(), $statement->value()->raw());
                $this->visit($parent, $depth + 1);
                break;
            }
        }
        unset($this->visiting[$source->canonicalPath()]);
    }
}
