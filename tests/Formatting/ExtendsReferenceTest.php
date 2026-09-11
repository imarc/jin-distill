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
}
