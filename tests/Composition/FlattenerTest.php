<?php

namespace JinDistill\Tests\Composition;

use JinDistill\Analysis\Analyzer;
use JinDistill\Analysis\SourceGraphBuilder;
use JinDistill\Composition\Flattener;
use JinDistill\Decoders\JinDecoder;
use JinDistill\Formatting\FormatOptions;
use JinDistill\Formatting\LeadingCommentPolicy;
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

    public function testItRendersDeepPathsUnderNestedSections(): void
    {
        $analysis = (new Analyzer(new SourceGraphBuilder(
            new MemorySourceLoader([]),
            new JinDecoder(),
            [new RelativeExtendsResolver()],
        )))->analyze("[form.fields]\nname = CPA", new SourceId('/project/child.jin', 'child.jin'));

        self::assertSame("[form.fields]\n\tname = CPA\n", (new Flattener())->flatten($analysis)->content());
    }

    public function testItRendersWinningLeadingComments(): void
    {
        $analysis = (new Analyzer(new SourceGraphBuilder(
            new MemorySourceLoader([]),
            new JinDecoder(),
            [new RelativeExtendsResolver()],
        )))->analyze("; Public name\nname = CPA", new SourceId('/project/child.jin', 'child.jin'));

        self::assertSame("; Public name\nname = CPA\n", (new Flattener())->flatten($analysis)->content());
    }

    public function testItUsesParentLeadingCommentWhenOverrideHasNone(): void
    {
        $parent = new SourceId('/project/base.jin', 'base.jin');
        $analysis = (new Analyzer(new SourceGraphBuilder(
            new MemorySourceLoader([new LoadedSource($parent, "; Base name\nname = Base")]),
            new JinDecoder(),
            [new RelativeExtendsResolver()],
        )))->analyze("--extends = base.jin\nname = Child", new SourceId('/project/child.jin', 'child.jin'));

        self::assertSame("; Base name\nname = Child\n", (new Flattener())->flatten($analysis)->content());
    }

    public function testItPreservesBlankLinesBetweenLeadingCommentAndWinner(): void
    {
        $parent = new SourceId('/project/base.jin', 'base.jin');
        $analysis = (new Analyzer(new SourceGraphBuilder(
            new MemorySourceLoader([new LoadedSource($parent, "; Base name\n\nname = Base")]),
            new JinDecoder(),
            [new RelativeExtendsResolver()],
        )))->analyze("--extends = base.jin\nname = Child", new SourceId('/project/child.jin', 'child.jin'));

        self::assertSame("; Base name\n\nname = Child\n", (new Flattener())->flatten($analysis)->content());
    }

    public function testItPreservesLeadingTriviaBeforeSections(): void
    {
        $analysis = (new Analyzer(new SourceGraphBuilder(
            new MemorySourceLoader([]),
            new JinDecoder(),
            [new RelativeExtendsResolver()],
        )))->analyze("; Form heading\n\n[form]\nname = CPA", new SourceId('/project/child.jin', 'child.jin'));

        self::assertSame("; Form heading\n\n[form]\n\tname = CPA\n", (new Flattener())->flatten($analysis)->content());
    }

    public function testItPreservesLeadingTriviaWhenApplyingNestedRemoval(): void
    {
        $parent = new SourceId('/project/base.jin', 'base.jin');
        $analysis = (new Analyzer(new SourceGraphBuilder(
            new MemorySourceLoader([new LoadedSource($parent, "; Fields\n\nfields = {\"first\": true, \"second\": false}")]),
            new JinDecoder(),
            [new RelativeExtendsResolver()],
        )))->analyze("--extends = base.jin\n--without = [\"fields.first\"]", new SourceId('/project/child.jin', 'child.jin'));

        self::assertSame("; Fields\n\nfields = {\n\t\"second\": false,\n}\n", (new Flattener())->flatten($analysis)->content());
    }

    public function testItPreservesJsonMemberCommentsAndSpacingFromParent(): void
    {
        $parent = new SourceId('/project/base.jin', 'base.jin');
        $analysis = (new Analyzer(new SourceGraphBuilder(
            new MemorySourceLoader([new LoadedSource($parent, <<<'JIN'
[form]
fields = {
    "person": {

        ; Personal Information

        "avatar": false,
    },
}
JIN)]),
            new JinDecoder(),
            [new RelativeExtendsResolver()],
        )))->analyze(<<<'JIN'
--extends = base.jin
[form]
fields = {
    "person": {
        "middleName": true,
    },
}
JIN, new SourceId('/project/child.jin', 'child.jin'));

        self::assertStringContainsString("\t\t\t; Personal Information\n\n\t\t\t\"avatar\": false,", (new Flattener())->flatten($analysis)->content());
    }

    public function testLeadingCommentPolicySelectsTheNearestDefinitionAcrossInheritance(): void
    {
        $grandparent = new LoadedSource(new SourceId('/project/grandparent.jin', 'grandparent.jin'), <<< 'JIN'
; Grandparent heading
[form]
; Grandparent name
name = Grandparent
fields = {
    ; Grandparent number
    "number": 1,
}
JIN);
        $parent = new LoadedSource(new SourceId('/project/parent.jin', 'parent.jin'), "--extends = grandparent.jin\n[form]\nname = Parent\nfields = {\"number\": 2}");
        $analysis = (new Analyzer(new SourceGraphBuilder(
            new MemorySourceLoader([$grandparent, $parent]),
            new JinDecoder(),
            [new RelativeExtendsResolver()],
        )))->analyze("--extends = parent.jin\n[form]\nname = Child\nfields = {\"number\": 3}", new SourceId('/project/child.jin', 'child.jin'));

        $nearest = (new Flattener())->flatten($analysis)->content();
        $winner = (new Flattener())->flatten($analysis, (new FormatOptions())->withLeadingComments(LeadingCommentPolicy::WinnerOnly))->content();

        self::assertStringContainsString('; Grandparent heading', $nearest);
        self::assertStringContainsString('; Grandparent name', $nearest);
        self::assertStringContainsString('; Grandparent number', $nearest);
        self::assertStringNotContainsString('Grandparent heading', $winner);
        self::assertStringNotContainsString('Grandparent name', $winner);
        self::assertStringNotContainsString('Grandparent number', $winner);
    }
}
