<?php

namespace JinDistill\Tests\Source;

use JinDistill\Source\FunctionExtendsResolver;
use JinDistill\Source\SourceId;
use JinDistill\Syntax\Value;
use JinDistill\Syntax\ValueKind;
use PHPUnit\Framework\TestCase;

final class FunctionExtendsResolverTest extends TestCase
{
    public function testItResolvesOneConfiguredStaticFunctionWithoutInvokingIt(): void
    {
        $resolver = new FunctionExtendsResolver('file', '/application');
        $value = new Value('file(vendor/package/base.jin)', ValueKind::Opaque, null, false);

        self::assertTrue($resolver->supports($value));
        self::assertSame(
            '/application/vendor/package/base.jin',
            $resolver->resolve($value, new SourceId('/application/config/child.jin', 'config/child.jin')),
        );
    }

    public function testItRejectsOtherFunctionsAndDynamicArguments(): void
    {
        $resolver = new FunctionExtendsResolver('file', '/application');

        self::assertFalse($resolver->supports(new Value('env(BASE)', ValueKind::Opaque, null, false)));
        self::assertFalse($resolver->supports(new Value('file(env(BASE))', ValueKind::Opaque, null, false)));
    }
}
