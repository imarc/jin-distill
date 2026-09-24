<?php

namespace JinDistill\Tests\Composition;

use Dotink\Jin\Parser;
use JinDistill\Evaluation\JinEvaluator;
use JinDistill\Formatting\Profiles\ImarcStyle;
use JinDistill\JinDistiller;
use PHPUnit\Framework\TestCase;

final class GoldenFlattenTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../fixtures/flatten';

    public function testItFlattensRealisticInheritanceWithMetadata(): void
    {
        $root = realpath(self::FIXTURES);
        self::assertNotFalse($root);
        $original = $root . '/config/forms/application.jin';
        $distiller = (new JinDistiller())
            ->withApplicationRoot($root)
            ->withAllowedRoots([$root])
            ->withEvaluator(new JinEvaluator(static fn (): Parser => new Parser([], [
                'file' => static fn (string $path): string => $root . '/' . $path,
            ])));

        $flattened = $distiller->flattenFile($original, ImarcStyle::v1())->content();

        self::assertSame(file_get_contents($root . '/expected.jin'), $flattened);
        self::assertStringNotContainsString('--extends', $flattened);
        self::assertStringNotContainsString('--without', $flattened);
        self::assertTrue($distiller->verifyFileSemantics($original, $flattened)->isEquivalent());
    }
}
