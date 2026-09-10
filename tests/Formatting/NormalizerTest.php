<?php

namespace JinDistill\Tests\Formatting;

use JinDistill\Analysis\Analyzer;
use JinDistill\Analysis\SourceGraphBuilder;
use JinDistill\Decoders\JinDecoder;
use JinDistill\Formatting\Normalizer;
use JinDistill\Source\MemorySourceLoader;
use JinDistill\Source\FilesystemSourceLoader;
use JinDistill\Source\PathPolicy;
use JinDistill\Source\RelativeExtendsResolver;
use JinDistill\Source\SourceId;
use PHPUnit\Framework\TestCase;

final class NormalizerTest extends TestCase
{
    public function testItReturnsCanonicalLocalContentAndItsAnalysis(): void
    {
        $normalizer = new Normalizer(new Analyzer(new SourceGraphBuilder(
            new MemorySourceLoader([]),
            new JinDecoder(),
            [new RelativeExtendsResolver()],
        )));

        $result = $normalizer->normalize('name=CPA', new SourceId('memory://input.jin', 'input.jin'));

        self::assertSame("name = CPA\n", $result->content());
        self::assertSame('memory://input.jin', array_values($result->analysis()->sourceGraph()->documents())[0]->source()->canonicalPath());
        self::assertSame([], $result->diagnostics());
    }

    public function testItNormalizesOnlyTheRequestedFile(): void
    {
        $child = __DIR__ . '/../fixtures/child.jin';
        $parent = __DIR__ . '/../fixtures/base.jin';
        $before = file_get_contents($parent);
        $normalizer = new Normalizer(new Analyzer(new SourceGraphBuilder(
            new FilesystemSourceLoader(new PathPolicy([realpath(dirname($child))])),
            new JinDecoder(),
            [new RelativeExtendsResolver()],
        )));

        $result = $normalizer->normalizeFile($child);

        self::assertStringContainsString("--extends = file(base.jin)\n", $result->content());
        self::assertStringContainsString("\tname = CPA\n", $result->content());
        self::assertSame($before, file_get_contents($parent));
    }

    public function testItIsIdempotentWithoutEvaluatingExpressions(): void
    {
        $normalizer = new Normalizer(new Analyzer(new SourceGraphBuilder(
            new MemorySourceLoader([]),
            new JinDecoder(),
            [new RelativeExtendsResolver()],
        )));
        $source = new SourceId('memory://input.jin', 'input.jin');
        $first = $normalizer->normalize("value=run( keep  this )\n[form]\nname=CPA", $source)->content();

        $second = $normalizer->normalize($first, $source)->content();

        self::assertSame($first, $second);
        self::assertStringContainsString('run( keep  this )', $second);
    }
}
