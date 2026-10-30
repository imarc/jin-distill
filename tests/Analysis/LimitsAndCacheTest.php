<?php

namespace JinDistill\Tests\Analysis;

use JinDistill\Analysis\AnalysisCacheKey;
use JinDistill\Analysis\AnalysisLimits;
use JinDistill\Analysis\Analyzer;
use JinDistill\Analysis\InMemoryAnalysisCache;
use JinDistill\Analysis\SourceGraphBuilder;
use JinDistill\Decoders\JinDecoder;
use JinDistill\Exceptions\AnalysisLimitException;
use JinDistill\Reporting\SchemaVersion;
use JinDistill\Source\LoadedSource;
use JinDistill\Source\MemorySourceLoader;
use JinDistill\Source\RelativeExtendsResolver;
use JinDistill\Source\SourceId;
use PHPUnit\Framework\TestCase;

final class LimitsAndCacheTest extends TestCase
{
    public function testItRejectsSourcesLargerThanTheConfiguredByteLimit(): void
    {
        $this->expectException(AnalysisLimitException::class);
        $this->expectExceptionMessage('bytes');

        $this->analyzer(new AnalysisLimits(bytes: 8))->analyze('name = a value too long', $this->source());
    }

    public function testItRejectsSyntaxDeeperThanTheConfiguredLimit(): void
    {
        $this->expectException(AnalysisLimitException::class);
        $this->expectExceptionMessage('syntax depth');

        $this->analyzer(new AnalysisLimits(syntaxDepth: 2))->analyze('a.b.c.d = true', $this->source());
    }

    public function testItRejectsInheritanceDeeperThanTheConfiguredLimit(): void
    {
        $grandparent = new SourceId('/project/grandparent.jin', 'grandparent.jin');
        $parent = new SourceId('/project/parent.jin', 'parent.jin');
        $loader = new MemorySourceLoader([
            new LoadedSource($parent, "--extends = grandparent.jin\nname = Parent"),
            new LoadedSource($grandparent, 'name = Grandparent'),
        ]);

        $this->expectException(AnalysisLimitException::class);
        $this->expectExceptionMessage('inheritance depth');

        $this->analyzer(new AnalysisLimits(inheritanceDepth: 1), $loader)
            ->analyze("--extends = parent.jin\nname = Child", new SourceId('/project/child.jin', 'child.jin'));
    }

    public function testItReusesAnalysisForIdenticalContent(): void
    {
        $cache = new InMemoryAnalysisCache();
        $analyzer = $this->analyzer(null, null, $cache);

        $first = $analyzer->analyze('name = CPA', $this->source());
        $second = $analyzer->analyze('name = CPA', $this->source());
        $analyzer->analyze('name = Other', $this->source());

        self::assertSame($first, $second);
        self::assertSame(2, $cache->count());
    }

    public function testCacheKeysCarryTheAnalysisSchemaVersion(): void
    {
        $key = AnalysisCacheKey::for('name = CPA', $this->source());

        self::assertStringContainsString(SchemaVersion::V1, $key);
        self::assertNotSame($key, AnalysisCacheKey::for('name = Other', $this->source()));
    }

    public function testItNeverCachesEvaluatedResults(): void
    {
        $cache = new InMemoryAnalysisCache();
        $analysis = $this->analyzer(null, null, $cache)->analyze('name = CPA', $this->source());

        $cache->put(AnalysisCacheKey::for('name = CPA', $this->source()), $analysis);

        self::assertSame(\JinDistill\Analysis\AnalysisMode::SourceOnly, $cache->get(AnalysisCacheKey::for('name = CPA', $this->source()))?->mode());
        self::assertNull($cache->get('missing'));
    }

    public function testItCapsCollectedDiagnostics(): void
    {
        $analysis = $this->analyzer()->analyze("[form]\nname=CPA\nlabel=CPA", $this->source());
        $rules = \JinDistill\Validation\ValidationRules::imarcV1()->withMaxDiagnostics(1);

        self::assertCount(1, (new \JinDistill\Validation\Validator())->validate($analysis, $rules));
    }

    private function analyzer(?AnalysisLimits $limits = null, ?MemorySourceLoader $loader = null, ?InMemoryAnalysisCache $cache = null): Analyzer
    {
        return new Analyzer(
            new SourceGraphBuilder($loader ?? new MemorySourceLoader([]), new JinDecoder(), [new RelativeExtendsResolver()], $limits),
            $limits,
            $cache,
        );
    }

    private function source(): SourceId
    {
        return new SourceId('memory://input.jin', 'input.jin');
    }
}
