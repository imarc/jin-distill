<?php

namespace JinDistill\Tests\Analysis;

use JinDistill\Analysis\AnalysisMode;
use JinDistill\Analysis\Analyzer;
use JinDistill\Analysis\SourceGraphBuilder;
use JinDistill\Decoders\JinDecoder;
use JinDistill\Source\LoadedSource;
use JinDistill\Source\MemorySourceLoader;
use JinDistill\Source\RelativeExtendsResolver;
use JinDistill\Source\SourceId;
use PHPUnit\Framework\TestCase;

final class AnalyzerTest extends TestCase
{
    public function testItReturnsASourceOnlyResultWithoutEvaluation(): void
    {
        $analyzer = new Analyzer(new SourceGraphBuilder(
            new MemorySourceLoader([]),
            new JinDecoder(),
            [new RelativeExtendsResolver()],
        ));

        $result = $analyzer->analyze('name = CPA', new SourceId('memory://input.jin', 'input.jin'));

        self::assertSame(AnalysisMode::SourceOnly, $result->mode());
        self::assertCount(1, $result->sourceGraph()->documents());
        self::assertSame([], $result->diagnostics());
    }

    public function testItDerivesWinnerAndOverrideProvenanceFromTheSourceGraph(): void
    {
        $parent = new SourceId('/project/base.jin', 'base.jin');
        $child = new SourceId('/project/child.jin', 'child.jin');
        $analyzer = new Analyzer(new SourceGraphBuilder(
            new MemorySourceLoader([new LoadedSource($parent, 'name = Base')]),
            new JinDecoder(),
            [new RelativeExtendsResolver()],
        ));

        $result = $analyzer->analyze('--extends = base.jin' . "\n" . 'name = Child', $child);
        $lineage = $result->provenance()->lineage(\JinDistill\Source\Path::fromSegments(['name']));

        self::assertSame('/project/child.jin', $lineage?->winner()->source()->canonicalPath());
        self::assertSame(['/project/base.jin'], array_map(
            static fn ($definition): string => $definition->source()->canonicalPath(),
            $lineage?->overridden() ?? [],
        ));
    }

    public function testItRetainsWithoutDirectivesAsLineageRemovals(): void
    {
        $parent = new SourceId('/project/base.jin', 'base.jin');
        $child = new SourceId('/project/child.jin', 'child.jin');
        $analyzer = new Analyzer(new SourceGraphBuilder(
            new MemorySourceLoader([new LoadedSource($parent, 'name = Base' . "\n" . 'age = 10')]),
            new JinDecoder(),
            [new RelativeExtendsResolver()],
        ));

        $result = $analyzer->analyze('--extends = base.jin' . "\n" . '--without = age', $child);
        $lineage = $result->provenance()->lineage(\JinDistill\Source\Path::fromSegments(['age']));

        self::assertSame('/project/base.jin', $lineage?->winner()->source()->canonicalPath());
        self::assertSame(['/project/child.jin'], array_map(
            static fn ($definition): string => $definition->source()->canonicalPath(),
            $lineage?->removals() ?? [],
        ));
    }

    public function testItKeepsSameSourceDeclarationsInResolutionOrder(): void
    {
        $source = new SourceId('memory://input.jin', 'input.jin');
        $analyzer = new Analyzer(new SourceGraphBuilder(
            new MemorySourceLoader([]),
            new JinDecoder(),
            [new RelativeExtendsResolver()],
        ));

        $result = $analyzer->analyze('name = First' . "\n" . 'name = Second', $source);
        $lineage = $result->provenance()->lineage(\JinDistill\Source\Path::fromSegments(['name']));

        self::assertSame('memory://input.jin', $lineage?->winner()->source()->canonicalPath());
        self::assertCount(1, $lineage?->overridden() ?? []);
        self::assertSame(
            1,
            $lineage?->overridden()[0]->span()->toArray()['start']['line'],
        );
        self::assertSame(2, $lineage?->winner()->span()->toArray()['start']['line']);
    }
}
