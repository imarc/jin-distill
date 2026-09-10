<?php

namespace JinDistill\Tests\Analysis;

use JinDistill\Analysis\SourceGraphBuilder;
use JinDistill\Decoders\JinDecoder;
use JinDistill\Exceptions\CircularInheritanceException;
use JinDistill\Source\LoadedSource;
use JinDistill\Source\MemorySourceLoader;
use JinDistill\Source\RelativeExtendsResolver;
use JinDistill\Source\SourceId;
use PHPUnit\Framework\TestCase;

final class SourceGraphBuilderTest extends TestCase
{
    public function testItKeepsParentAndChildDocumentsDistinctWithAnInheritanceEdge(): void
    {
        $parent = new SourceId('/project/base.jin', 'base.jin');
        $child = new SourceId('/project/child.jin', 'child.jin');
        $builder = new SourceGraphBuilder(
            new MemorySourceLoader([new LoadedSource($parent, 'name = Base')]),
            new JinDecoder(),
            [new RelativeExtendsResolver()],
        );

        $graph = $builder->build('--extends = base.jin' . "\n" . 'name = Child', $child);

        self::assertCount(2, $graph->documents());
        self::assertCount(1, $graph->edges());
        self::assertSame('/project/child.jin', $graph->edges()[0]->child()->canonicalPath());
        self::assertSame('/project/base.jin', $graph->edges()[0]->parent()->canonicalPath());
        self::assertSame('base.jin', $graph->edges()[0]->reference());
    }

    public function testItRejectsCircularInheritance(): void
    {
        $child = new SourceId('/project/child.jin', 'child.jin');
        $parent = new SourceId('/project/base.jin', 'base.jin');
        $builder = new SourceGraphBuilder(
            new MemorySourceLoader([
                new LoadedSource($parent, '--extends = child.jin'),
                new LoadedSource($child, '--extends = base.jin'),
            ]),
            new JinDecoder(),
            [new RelativeExtendsResolver()],
        );

        $this->expectException(CircularInheritanceException::class);

        $builder->build('--extends = base.jin', $child);
    }
}
