<?php

namespace JinDistill\Tests;

use JinDistill\Decoders\JinDecoder;
use JinDistill\Exceptions\InvalidStructureException;
use JinDistill\JinResolver;
use PHPUnit\Framework\TestCase;

final class JinResolverTest extends TestCase
{
    public function testItResolvesExtendsWithoutAndChildOverrides(): void
    {
        $document = (new JinResolver(new JinDecoder()))->resolveFile(__DIR__ . '/fixtures/child.jin');

        self::assertSame('CPA', $document->data['form']['name']);
        self::assertTrue($document->data['form']['fields']['person']['firstName']);
        self::assertTrue($document->data['form']['fields']['person']['lastName']);
        self::assertArrayNotHasKey('avatar', $document->data['form']['fields']['person']);
        self::assertArrayNotHasKey('middleName', $document->data['form']['fields']['person']);
        self::assertTrue($document->data['form']['fields']['person']['birthDate']);
        self::assertSame([], $document->directives);
    }

    public function testItDetectsCircularExtends(): void
    {
        $this->expectException(InvalidStructureException::class);

        (new JinResolver(new JinDecoder()))->resolveFile(__DIR__ . '/fixtures/circular-a.jin');
    }
}
