<?php

namespace JinDistill\Tests\Source;

use JinDistill\Source\RelativeExtendsResolver;
use JinDistill\Source\SourceId;
use JinDistill\Syntax\Value;
use JinDistill\Syntax\ValueKind;
use PHPUnit\Framework\TestCase;

final class RelativeExtendsResolverTest extends TestCase
{
    public function testItResolvesStaticBareAndQuotedExtendsValuesRelativeToTheChildSource(): void
    {
        $resolver = new RelativeExtendsResolver();
        $child = new SourceId('/project/config/child.jin', 'config/child.jin');

        self::assertTrue($resolver->supports(new Value('base.jin', ValueKind::Scalar, 'base.jin', true)));
        self::assertTrue($resolver->supports(new Value('"base.jin"', ValueKind::Scalar, 'base.jin', true)));
        self::assertSame('/project/config/base.jin', $resolver->resolve(new Value('base.jin', ValueKind::Scalar, 'base.jin', true), $child));
        self::assertSame('/project/config/base.jin', $resolver->resolve(new Value('"base.jin"', ValueKind::Scalar, 'base.jin', true), $child));
    }

    public function testItRefusesDynamicExtendsValues(): void
    {
        $resolver = new RelativeExtendsResolver();

        self::assertFalse($resolver->supports(new Value('file(base.jin)', ValueKind::Opaque, null, false)));
    }
}
