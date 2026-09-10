<?php

namespace JinDistill\Tests\Composition;

use JinDistill\Analysis\Analyzer;
use JinDistill\Analysis\SourceGraphBuilder;
use JinDistill\Composition\DefinitionComposer;
use JinDistill\Decoders\JinDecoder;
use JinDistill\Exceptions\UnflattenableDefinitionException;
use JinDistill\Source\LoadedSource;
use JinDistill\Source\MemorySourceLoader;
use JinDistill\Source\RelativeExtendsResolver;
use JinDistill\Source\SourceId;
use PHPUnit\Framework\TestCase;

final class DynamicCompositionTest extends TestCase
{
    public function testItRejectsNestedOverlaysBelowOpaqueAssignments(): void
    {
        $parent = new SourceId('/project/base.jin', 'base.jin');
        $child = new SourceId('/project/child.jin', 'child.jin');
        $analysis = (new Analyzer(new SourceGraphBuilder(
            new MemorySourceLoader([new LoadedSource($parent, 'fields = run(buildFields())')]),
            new JinDecoder(),
            [new RelativeExtendsResolver()],
        )))->analyze("--extends = base.jin\nfields.required = true", $child);

        try {
            (new DefinitionComposer())->compose($analysis);
            self::fail('Expected dynamic composition conflict.');
        } catch (UnflattenableDefinitionException $error) {
            self::assertSame('/fields', $error->conflicts()[0]->parentPath());
            self::assertSame('/fields/required', $error->conflicts()[0]->childPath());
        }
    }
}
