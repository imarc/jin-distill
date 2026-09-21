<?php

namespace JinDistill\Tests\Diff;

use JinDistill\Analysis\Analyzer;
use JinDistill\Analysis\SourceGraphBuilder;
use JinDistill\Composition\DefinitionComposer;
use JinDistill\Decoders\JinDecoder;
use JinDistill\Diff\Differ;
use JinDistill\Diff\DiffOptions;
use JinDistill\Exceptions\UndiffableDefinitionException;
use JinDistill\Source\LoadedSource;
use JinDistill\Source\MemorySourceLoader;
use JinDistill\Source\Path;
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

        self::assertSame("--extends = file(base.jin)\n\nname = Child\n", $result->content());
    }

    public function testItEmitsOnlyExtendsForIdenticalDefinitions(): void
    {
        $result = (new Differ())->diff($this->compose('name = Base'), $this->compose('name = Base'), 'base.jin');

        self::assertSame("--extends = file(base.jin)\n", $result->content());
    }

    public function testItScopesRemovalsToOverriddenJsonLists(): void
    {
        $parent = $this->compose("[checkout]\nnotifications = [\"Parent\"]\ntest = value");
        $target = $this->compose("[checkout]\nnotifications = [\"Child\"]");

        $result = (new Differ())->diff($parent, $target, 'base.jin');

        self::assertSame(
            ['checkout.notifications'],
            array_map(static fn ($path): string => implode('.', $path->segments()), $result->removals()),
        );
        self::assertStringContainsString("--without = [\n\t\"checkout.notifications\",\n]\n", $result->content());
        self::assertStringNotContainsString('"checkout"', $result->content());
        self::assertStringNotContainsString('checkout.test', $result->content());
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

    public function testItRejectsNestedOverridesOfOpaqueParentAssignments(): void
    {
        $parent = $this->compose('fields = run(build())');
        $target = $this->compose("[fields]\nrequired = true");

        try {
            (new Differ())->diff($parent, $target, 'base.jin');
            self::fail('Expected UndiffableDefinitionException.');
        } catch (UndiffableDefinitionException $exception) {
            self::assertSame('/fields', $exception->conflicts()[0]->parentPath());
            self::assertSame('/fields/required', $exception->conflicts()[0]->childPath());
        }
    }

    public function testItReportsProvenanceForEmittedAssignments(): void
    {
        $result = (new Differ())->diff($this->compose('name = Base'), $this->compose('name = Child'), 'base.jin');

        self::assertNotNull($result->provenance()->lineage(Path::fromSegments(['name'])));
    }

    public function testItDiagnosesCopiedOpaqueExpressions(): void
    {
        $result = (new Differ())->diff($this->compose('name = Base'), $this->compose("name = Base\nfields = run(build())"), 'base.jin');

        self::assertSame(['jin.diff.opaque-copy'], array_map(static fn ($diagnostic): string => $diagnostic->ruleId(), $result->diagnostics()));
        self::assertSame('/fields', $result->diagnostics()[0]->path()?->toJsonPointer());
    }

    public function testItOmitsVerificationUnlessRequested(): void
    {
        $result = (new Differ())->diff($this->compose('name = Base'), $this->compose('name = Child'), 'base.jin');

        self::assertNull($result->verification());
    }

    public function testItVerifiesGeneratedOverlayPreservesParentOnlyAssignments(): void
    {
        $parent = $this->compose("name = Base\nenabled = true\n[form]\nlabel = Parent");
        $target = $this->compose("name = Child\n[form]\nlabel = Child");

        $result = (new Differ())->diff($parent, $target, 'base.jin', (new DiffOptions())->withVerification(true));

        self::assertTrue($result->verification()?->isEquivalent());
        self::assertSame([], $result->verification()->differences());
    }

    public function testItComparesEffectiveDefinitionsFromInheritedInputs(): void
    {
        $parent = $this->composeInherited('parent', "name = Base\nenabled = true", "--extends = parent-base.jin\nenabled = false");
        $target = $this->composeInherited('target', "name = Base\nenabled = true", "--extends = target-base.jin\nname = Child");

        $result = (new Differ())->diff($parent, $target, 'parent.jin', (new DiffOptions())->withVerification(true));

        self::assertStringContainsString("enabled = true\n", $result->content());
        self::assertStringContainsString("name = Child\n", $result->content());
        self::assertSame([], $result->verification()?->differences());
        self::assertSame(
            '/project/target.jin',
            $result->provenance()->lineage(Path::fromSegments(['name']))?->winner()->source()->canonicalPath(),
        );
        self::assertSame(
            '/project/target-base.jin',
            $result->provenance()->lineage(Path::fromSegments(['enabled']))?->winner()->source()->canonicalPath(),
        );
    }

    private function composeInherited(string $name, string $base, string $child): \JinDistill\Composition\ComposedDocument
    {
        $baseId = new SourceId('/project/' . $name . '-base.jin', $name . '-base.jin');
        $analysis = (new Analyzer(new SourceGraphBuilder(
            new MemorySourceLoader([new LoadedSource($baseId, $base)]),
            new JinDecoder(),
            [new RelativeExtendsResolver()],
        )))->analyze($child, new SourceId('/project/' . $name . '.jin', $name . '.jin'));

        return (new DefinitionComposer())->compose($analysis);
    }

    private function compose(string $contents): \JinDistill\Composition\ComposedDocument
    {
        $analysis = (new Analyzer(new SourceGraphBuilder(new MemorySourceLoader([]), new JinDecoder(), [new RelativeExtendsResolver()])))
            ->analyze($contents, new SourceId('memory://input.jin', 'input.jin'));
        return (new DefinitionComposer())->compose($analysis);
    }
}
