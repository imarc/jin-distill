<?php

namespace JinDistill\Tests\Diff;

use JinDistill\Analysis\Analyzer;
use JinDistill\Analysis\SourceGraphBuilder;
use JinDistill\Composition\DefinitionComposer;
use JinDistill\Decoders\JinDecoder;
use JinDistill\Diff\DefinitionDiffer;
use JinDistill\Diff\RemovalPlanner;
use JinDistill\Source\MemorySourceLoader;
use JinDistill\Source\RelativeExtendsResolver;
use JinDistill\Source\SourceId;
use PHPUnit\Framework\TestCase;

final class RemovalPlannerTest extends TestCase
{
    public function testItLeavesParentOnlyAssignmentsInherited(): void
    {
        $parent = $this->compose("name = Base\nenabled = true");
        $target = $this->compose('name = Base');
        $differences = (new DefinitionDiffer())->compare($parent, $target);

        $paths = (new RemovalPlanner())->plan($parent, $target, $differences);

        self::assertSame([], $paths);
    }

    public function testItRemovesChangedListsBeforeOverlayingTarget(): void
    {
        $parent = $this->compose('items = [1, 2]');
        $target = $this->compose('items = [3]');
        $differences = (new DefinitionDiffer())->compare($parent, $target);

        $paths = (new RemovalPlanner())->plan($parent, $target, $differences);

        self::assertSame(['/items'], array_map(static fn ($path): string => $path->toJsonPointer(), $paths));
    }

    public function testItDoesNotCollapseChangedListsIntoTheirIniSection(): void
    {
        $parent = $this->compose("[form]\na = [1]\nb = [2]");
        $target = $this->compose("[form]\na = [3]\nb = [4]");
        $differences = (new DefinitionDiffer())->compare($parent, $target);

        $paths = (new RemovalPlanner())->plan($parent, $target, $differences);

        self::assertSame(['/form/a', '/form/b'], array_map(static fn ($path): string => $path->toJsonPointer(), $paths));
    }

    public function testItKeepsSoleChangedListAtItsJsonBoundary(): void
    {
        $parent = $this->compose("[cancellation]\npolicies = [1]");
        $target = $this->compose("[cancellation]\npolicies = [2]");
        $differences = (new DefinitionDiffer())->compare($parent, $target);

        $paths = (new RemovalPlanner())->plan($parent, $target, $differences);

        self::assertSame(['/cancellation/policies'], array_map(static fn ($path): string => $path->toJsonPointer(), $paths));
    }

    private function compose(string $contents): \JinDistill\Composition\ComposedDocument
    {
        $analysis = (new Analyzer(new SourceGraphBuilder(
            new MemorySourceLoader([]),
            new JinDecoder(),
            [new RelativeExtendsResolver()],
        )))->analyze($contents, new SourceId('memory://input.jin', 'input.jin'));

        return (new DefinitionComposer())->compose($analysis);
    }
}
