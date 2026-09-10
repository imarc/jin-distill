<?php

namespace JinDistill\Tests\Analysis;

use JinDistill\Analysis\Definition;
use JinDistill\Analysis\Lineage;
use JinDistill\Analysis\ProvenanceIndex;
use JinDistill\Source\Path;
use JinDistill\Source\SourceId;
use JinDistill\Source\SourceSpan;
use PHPUnit\Framework\TestCase;

final class ProvenanceIndexTest extends TestCase
{
    public function testItFindsAPathLineageWithoutConflatingLiteralDots(): void
    {
        $path = Path::fromSegments(['form', 'person.name']);
        $parent = new Definition($path, new SourceId('/parent.jin', 'parent.jin'), $this->span('/parent.jin'));
        $child = new Definition($path, new SourceId('/child.jin', 'child.jin'), $this->span('/child.jin'));
        $index = new ProvenanceIndex([new Lineage($child, [$parent], [])]);

        $lineage = $index->lineage(Path::fromSegments(['form', 'person.name']));

        self::assertSame($child, $lineage?->winner());
        self::assertSame([$parent], $lineage?->overridden());
        self::assertNull($index->lineage(Path::fromSegments(['form', 'person', 'name'])));
    }

    private function span(string $source): SourceSpan
    {
        $id = new SourceId($source, basename($source));

        return new SourceSpan($id, 1, 1, 1, 10);
    }
}
