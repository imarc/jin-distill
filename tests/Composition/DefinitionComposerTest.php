<?php

namespace JinDistill\Tests\Composition;

use JinDistill\Analysis\Analyzer;
use JinDistill\Analysis\SourceGraphBuilder;
use JinDistill\Composition\DefinitionComposer;
use JinDistill\Decoders\JinDecoder;
use JinDistill\Source\LoadedSource;
use JinDistill\Source\MemorySourceLoader;
use JinDistill\Source\RelativeExtendsResolver;
use JinDistill\Source\SourceId;
use JinDistill\Syntax\Assignment;
use PHPUnit\Framework\TestCase;

final class DefinitionComposerTest extends TestCase
{
    public function testItKeepsParentOrderWhileApplyingChildOverrides(): void
    {
        $parent = new SourceId('/project/base.jin', 'base.jin');
        $child = new SourceId('/project/child.jin', 'child.jin');
        $analysis = (new Analyzer(new SourceGraphBuilder(
            new MemorySourceLoader([new LoadedSource($parent, "name = Base\nenabled = false")]),
            new JinDecoder(),
            [new RelativeExtendsResolver()],
        )))->analyze("--extends = base.jin\nname = Child\nnew = true", $child);

        $document = (new DefinitionComposer())->compose($analysis)->document();
        $assignments = array_values(array_filter($document->statements(), static fn ($node): bool => $node instanceof Assignment));

        self::assertSame(['name', 'enabled', 'new'], array_map(static fn (Assignment $node): string => $node->path()->segments()[0], $assignments));
        self::assertSame('Child', $assignments[0]->value()->staticValue());
        self::assertSame('/project/child.jin', $assignments[0]->span()->toArray()['source']);
    }

    public function testItRemovesInheritedDefinitionsNamedByWithout(): void
    {
        $parent = new SourceId('/project/base.jin', 'base.jin');
        $child = new SourceId('/project/child.jin', 'child.jin');
        $analysis = (new Analyzer(new SourceGraphBuilder(
            new MemorySourceLoader([new LoadedSource($parent, "name = Base\nage = 10")]),
            new JinDecoder(),
            [new RelativeExtendsResolver()],
        )))->analyze("--extends = base.jin\n--without = age", $child);

        $document = (new DefinitionComposer())->compose($analysis)->document();
        $assignments = array_values(array_filter($document->statements(), static fn ($node): bool => $node instanceof Assignment));

        self::assertSame(['name'], array_map(static fn (Assignment $node): string => $node->path()->segments()[0], $assignments));
    }
}
