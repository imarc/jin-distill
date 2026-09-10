<?php

namespace JinDistill\Tests\Formatting;

use JinDistill\Analysis\Analyzer;
use JinDistill\Analysis\SourceGraphBuilder;
use JinDistill\Decoders\JinDecoder;
use JinDistill\Formatting\Normalizer;
use JinDistill\Source\MemorySourceLoader;
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
}
