<?php

namespace JinDistill\Tests\Diff;

use JinDistill\Analysis\Analyzer;
use JinDistill\Analysis\SourceGraphBuilder;
use JinDistill\Composition\DefinitionComposer;
use JinDistill\Decoders\JinDecoder;
use JinDistill\Diff\Differ;
use JinDistill\Source\MemorySourceLoader;
use JinDistill\Source\RelativeExtendsResolver;
use JinDistill\Source\SourceId;
use PHPUnit\Framework\TestCase;

final class DifferTest extends TestCase
{
    public function testItGeneratesInheritanceWithChangedAssignments(): void
    {
        $parent = $this->compose('name = Base');
        $target = $this->compose('name = Child');

        $result = (new Differ())->diff($parent, $target, 'base.jin');

        self::assertSame("--extends = file(base.jin)\nname = Child\n", $result->content());
    }

    public function testItEmitsOnlyExtendsForIdenticalDefinitions(): void
    {
        $result = (new Differ())->diff($this->compose('name = Base'), $this->compose('name = Base'), 'base.jin');

        self::assertSame("--extends = file(base.jin)\n", $result->content());
    }

    public function testItEmitsPlannedRemovals(): void
    {
        $result = (new Differ())->diff($this->compose("name = Base\nenabled = true"), $this->compose('name = Base'), 'base.jin');

        self::assertStringContainsString("--without = [\n\t\"enabled\",\n]\n", $result->content());
    }

    public function testItRendersChangedNestedPathsInSections(): void
    {
        $result = (new Differ())->diff($this->compose("[form]\nname = Base"), $this->compose("[form]\nname = Child"), 'base.jin');

        self::assertStringContainsString("[form]\n\tname = Child\n", $result->content());
    }

    public function testItPreservesTargetLeadingComments(): void
    {
        $result = (new Differ())->diff($this->compose('name = Base'), $this->compose("; Public name\nname = Child"), 'base.jin');

        self::assertStringContainsString("; Public name\nname = Child\n", $result->content());
    }

    private function compose(string $contents): \JinDistill\Composition\ComposedDocument
    {
        $analysis = (new Analyzer(new SourceGraphBuilder(new MemorySourceLoader([]), new JinDecoder(), [new RelativeExtendsResolver()])))
            ->analyze($contents, new SourceId('memory://input.jin', 'input.jin'));
        return (new DefinitionComposer())->compose($analysis);
    }
}
