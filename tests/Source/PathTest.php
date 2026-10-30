<?php

namespace JinDistill\Tests\Source;

use JinDistill\Source\Path;
use PHPUnit\Framework\TestCase;

final class PathTest extends TestCase
{
    public function testItKeepsLiteralDotsInsideSegments(): void
    {
        $path = Path::fromSegments(['nesting', 'dotKey', 'butt.test']);

        self::assertSame(['nesting', 'dotKey', 'butt.test'], $path->segments());
        self::assertSame('/nesting/dotKey/butt.test', $path->toJsonPointer());
    }

    public function testItEscapesJsonPointerSegments(): void
    {
        $path = Path::fromSegments(['til~de', 'slash/value']);

        self::assertSame('/til~0de/slash~1value', $path->toJsonPointer());
    }

    public function testItAppendsWithoutMutatingOriginal(): void
    {
        $path = Path::fromSegments(['form']);

        self::assertSame(['form'], $path->segments());
        self::assertSame(['form', 'name'], $path->append('name')->segments());
    }
}
