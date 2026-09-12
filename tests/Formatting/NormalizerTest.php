<?php

namespace JinDistill\Tests\Formatting;

use JinDistill\Analysis\Analyzer;
use JinDistill\Analysis\SourceGraphBuilder;
use JinDistill\Decoders\JinDecoder;
use JinDistill\Evaluation\EvaluationOptions;
use JinDistill\Evaluation\SemanticVerifier;
use JinDistill\Formatting\Normalizer;
use JinDistill\Source\FilesystemSourceLoader;
use JinDistill\Source\MemorySourceLoader;
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
        self::assertSame('jin.style.canonical-layout', $result->diagnostics()[0]->ruleId());
        self::assertSame('name = CPA', $result->diagnostics()[0]->suggestion());
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

    public function testItsOutputCanBeExplicitlyVerifiedForSemanticEquivalence(): void
    {
        $normalizer = new Normalizer(new Analyzer(new SourceGraphBuilder(
            new MemorySourceLoader([]),
            new JinDecoder(),
            [new RelativeExtendsResolver()],
        )));
        $original = "name=CPA\n[form]\nenabled=true";
        $normalized = $normalizer->normalize($original, new SourceId('memory://input.jin', 'input.jin'))->content();

        self::assertTrue((new SemanticVerifier())->verify($original, $normalized, new EvaluationOptions())->isEquivalent());
    }
}
