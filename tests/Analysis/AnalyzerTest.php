<?php

namespace JinDistill\Tests\Analysis;

use JinDistill\Analysis\AnalysisMode;
use JinDistill\Analysis\Analyzer;
use JinDistill\Analysis\SourceGraphBuilder;
use JinDistill\Decoders\JinDecoder;
use JinDistill\Source\MemorySourceLoader;
use JinDistill\Source\RelativeExtendsResolver;
use JinDistill\Source\SourceId;
use PHPUnit\Framework\TestCase;

final class AnalyzerTest extends TestCase
{
    public function testItReturnsASourceOnlyResultWithoutEvaluation(): void
    {
        $analyzer = new Analyzer(new SourceGraphBuilder(
            new MemorySourceLoader([]),
            new JinDecoder(),
            [new RelativeExtendsResolver()],
        ));

        $result = $analyzer->analyze('name = CPA', new SourceId('memory://input.jin', 'input.jin'));

        self::assertSame(AnalysisMode::SourceOnly, $result->mode());
        self::assertCount(1, $result->sourceGraph()->documents());
        self::assertSame([], $result->diagnostics());
    }
}
