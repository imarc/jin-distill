<?php

namespace JinDistill\Tests\Formatting;

use JinDistill\Formatting\ExtendsPathStyle;
use JinDistill\Formatting\Profiles\DotinkStyle;
use JinDistill\Formatting\Profiles\ImarcStyle;
use PHPUnit\Framework\TestCase;

final class FormatOptionsTest extends TestCase
{
    public function testImarcV1HasVersionedSafeDefaults(): void
    {
        $options = ImarcStyle::v1();

        self::assertSame("\t", $options->indentation());
        self::assertSame("\n", $options->lineEnding());
        self::assertSame('decimal', $options->numericStyle());
        self::assertSame('minimal-safe', $options->stringQuoting());
        self::assertSame('explicit', $options->sectionReferences());
        self::assertSame('assignment', $options->diffGranularity());
        self::assertTrue($options->metadataSensitiveDiffs());
        self::assertSame(ExtendsPathStyle::HiraethFile, $options->extendsPathStyle());
    }

    public function testDotinkV1UsesBareRelativeExtendsPaths(): void
    {
        self::assertSame(ExtendsPathStyle::BareRelative, DotinkStyle::v1()->extendsPathStyle());
    }

    public function testWithMethodsReturnNewOptionsWithoutChangingTheProfileDefault(): void
    {
        $default = ImarcStyle::v1();
        $changed = $default->withIndentation('  ');

        self::assertNotSame($default, $changed);
        self::assertSame("\t", $default->indentation());
        self::assertSame('  ', $changed->indentation());
        self::assertSame("\t", ImarcStyle::v1()->indentation());
    }
}
