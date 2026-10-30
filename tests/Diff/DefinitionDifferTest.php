<?php

namespace JinDistill\Tests\Diff;

use JinDistill\Analysis\Analyzer;
use JinDistill\Analysis\SourceGraphBuilder;
use JinDistill\Composition\DefinitionComposer;
use JinDistill\Decoders\JinDecoder;
use JinDistill\Diff\DefinitionDiffer;
use JinDistill\Diff\DifferenceKind;
use JinDistill\Diff\DiffOptions;
use JinDistill\Source\MemorySourceLoader;
use JinDistill\Source\RelativeExtendsResolver;
use JinDistill\Source\SourceId;
use PHPUnit\Framework\TestCase;

final class DefinitionDifferTest extends TestCase
{
    public function testItClassifiesAssignmentDifferences(): void
    {
        $parent = $this->compose("name = Base\nenabled = true");
        $target = $this->compose("name = Child\nextra = true");

        $differences = (new DefinitionDiffer())->compare($parent, $target)->all();

        self::assertSame(
            [DifferenceKind::Changed, DifferenceKind::Removed, DifferenceKind::Added],
            array_map(static fn ($difference) => $difference->kind(), $differences),
        );
        self::assertSame(['/name', '/enabled', '/extra'], array_map(static fn ($difference): string => $difference->path()->toJsonPointer(), $differences));
    }

    public function testItClassifiesLeadingCommentChangesAsMetadataChanges(): void
    {
        $parent = $this->compose("; Base name\nname = CPA");
        $target = $this->compose("; Public name\nname = CPA");

        $difference = (new DefinitionDiffer())->compare($parent, $target)->all()[0];

        self::assertSame(DifferenceKind::MetadataChanged, $difference->kind());
        self::assertSame([], (new DefinitionDiffer())->compare($parent, $target, new DiffOptions(false))->all());
    }

    public function testItClassifiesOpaqueExpressionChangesWithoutEvaluation(): void
    {
        $difference = (new DefinitionDiffer())->compare($this->compose('value = run(parent())'), $this->compose('value = run(target())'))->all()[0];

        self::assertSame(DifferenceKind::Changed, $difference->kind());
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
