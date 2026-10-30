<?php

namespace JinDistill\Tests\Source;

use InvalidArgumentException;
use JinDistill\Source\FilesystemSourceLoader;
use JinDistill\Source\PathPolicy;
use PHPUnit\Framework\TestCase;

final class FilesystemSourceLoaderTest extends TestCase
{
    public function testItCanonicalizesSourcesAndRetainsTheirOriginalReference(): void
    {
        $fixtureRoot = realpath(__DIR__ . '/../fixtures');
        self::assertNotFalse($fixtureRoot);
        $loader = new FilesystemSourceLoader(new PathPolicy([$fixtureRoot]));

        $source = $loader->load('base.jin');

        self::assertSame($fixtureRoot . '/base.jin', $source->source()->canonicalPath());
        self::assertSame('base.jin', $source->source()->reference());
        self::assertStringContainsString('name = Default', $source->contents());
    }

    public function testItRejectsSourcesOutsideAllowedRoots(): void
    {
        $fixtureRoot = realpath(__DIR__ . '/../fixtures');
        self::assertNotFalse($fixtureRoot);
        $loader = new FilesystemSourceLoader(new PathPolicy([$fixtureRoot]));

        $this->expectException(InvalidArgumentException::class);

        $loader->load(__DIR__ . '/../../composer.json');
    }
}
