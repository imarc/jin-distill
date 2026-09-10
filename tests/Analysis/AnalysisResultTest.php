<?php

namespace JinDistill\Tests\Analysis;

use JinDistill\Analysis\AnalysisMode;
use JinDistill\Analysis\AnalysisResult;
use JinDistill\Analysis\SourceGraph;
use LogicException;
use PHPUnit\Framework\TestCase;

final class AnalysisResultTest extends TestCase
{
    public function testItExposesSourceOnlyAnalysisWithoutPretendingResolvedDataExists(): void
    {
        $result = AnalysisResult::sourceOnly(new SourceGraph([], []), []);

        self::assertSame(AnalysisMode::SourceOnly, $result->mode());
        self::assertSame([], $result->sourceGraph()->documents());
        self::assertSame([], $result->diagnostics());

        $this->expectException(LogicException::class);

        $result->resolvedData();
    }
}
