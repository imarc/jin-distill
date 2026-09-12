<?php

namespace JinDistill\Tests\Formatting;

use JinDistill\Exceptions\InvalidPathException;
use JinDistill\Formatting\ExtendsReference;
use JinDistill\Formatting\Profiles\DotinkStyle;
use JinDistill\Formatting\Profiles\ImarcStyle;
use PHPUnit\Framework\TestCase;

final class ExtendsReferenceTest extends TestCase
{
    public function testItBuildsOutputRelativeReferences(): void
    {
        $reference = ExtendsReference::forOutput('/project/forms/base.jin', '/project/forms/diff.jin');

        self::assertSame('base.jin', $reference->relativePath());
    }

    public function testItBuildsAncestorRelativeReferences(): void
    {
        $reference = ExtendsReference::forOutput('/project/base.jin', '/project/forms/diff.jin');

        self::assertSame('../base.jin', $reference->relativePath());
    }

    public function testItNormalizesWindowsSeparators(): void
    {
        self::assertSame('base.jin', ExtendsReference::forOutput('C:\\project\\base.jin', 'C:\\project\\diff.jin')->relativePath());
    }

    public function testItRendersBareRelativeSourceForDotinkStyle(): void
    {
        $reference = ExtendsReference::forOutput('/project/base.jin', '/project/forms/diff.jin');

        self::assertSame('../base.jin', $reference->toSource(DotinkStyle::v1()));
    }

    public function testItRendersApplicationRelativeFileSourceForImarcStyle(): void
    {
        $reference = ExtendsReference::forOutput('/project/config/forms/base.jin', '/project/config/forms/diff.jin', '/project');

        self::assertSame('file(config/forms/base.jin)', $reference->toSource(ImarcStyle::v1()));
    }

    public function testItRendersOutputRelativeFileSourceWithoutApplicationRoot(): void
    {
        $reference = ExtendsReference::forOutput('/project/base.jin', '/project/forms/diff.jin');

        self::assertSame('file(../base.jin)', $reference->toSource(ImarcStyle::v1()));
    }

    public function testItRejectsParentsOutsideTheApplicationRoot(): void
    {
        $this->expectException(InvalidPathException::class);

        ExtendsReference::forOutput('/elsewhere/base.jin', '/project/forms/diff.jin', '/project');
    }
}
