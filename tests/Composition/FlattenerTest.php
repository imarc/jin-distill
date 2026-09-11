<?php

namespace JinDistill\Tests\Composition;

use JinDistill\Analysis\Analyzer;
use JinDistill\Analysis\SourceGraphBuilder;
use JinDistill\Composition\Flattener;
use JinDistill\Decoders\JinDecoder;
use JinDistill\Source\LoadedSource;
use JinDistill\Source\MemorySourceLoader;
use JinDistill\Source\RelativeExtendsResolver;
use JinDistill\Source\SourceId;
use PHPUnit\Framework\TestCase;

final class FlattenerTest extends TestCase
{
    public function testItRendersStandaloneStaticComposition(): void
    {
        $parent = new SourceId('/project/base.jin', 'base.jin');
        $analysis = (new Analyzer(new SourceGraphBuilder(
            new MemorySourceLoader([new LoadedSource($parent, "name = Base\nenabled = true")]),
            new JinDecoder(),
            [new RelativeExtendsResolver()],
        )))->analyze("--extends = base.jin\nname = Child", new SourceId('/project/child.jin', 'child.jin'));

        $result = (new Flattener())->flatten($analysis);

        self::assertSame("name = Child\nenabled = true\n", $result->content());
        self::assertStringNotContainsString('--extends', $result->content());
        self::assertSame([], $result->diagnostics());
    }

    public function testItRendersNestedPathsUnderExplicitSections(): void
    {
        $analysis = (new Analyzer(new SourceGraphBuilder(
            new MemorySourceLoader([]),
            new JinDecoder(),
            [new RelativeExtendsResolver()],
        )))->analyze("[form]\nname = CPA", new SourceId('/project/child.jin', 'child.jin'));

        self::assertSame("[form]\n\tname = CPA\n", (new Flattener())->flatten($analysis)->content());
    }
}
