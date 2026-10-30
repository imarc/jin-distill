<?php

namespace JinDistill\Tests\Source;

use JinDistill\Source\LoadedSource;
use JinDistill\Source\MemorySourceLoader;
use JinDistill\Source\SourceId;
use PHPUnit\Framework\TestCase;

final class MemorySourceLoaderTest extends TestCase
{
    public function testItLoadsAnImmutableSourceByItsStableReference(): void
    {
        $source = new LoadedSource(
            new SourceId('memory://base.jin', 'base.jin'),
            'name = Base',
        );
        $loader = new MemorySourceLoader([$source]);

        $loaded = $loader->load('base.jin');

        self::assertSame('memory://base.jin', $loaded->source()->canonicalPath());
        self::assertSame('base.jin', $loaded->source()->reference());
        self::assertSame('name = Base', $loaded->contents());
    }
}
