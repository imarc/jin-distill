<?php

namespace JinDistill\Tests\Formatting;

use JinDistill\Formatting\ExtendsReference;
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
}
