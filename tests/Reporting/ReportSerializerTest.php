<?php

namespace JinDistill\Tests\Reporting;

use JinDistill\Diff\DiffOptions;
use JinDistill\JinDistiller;
use JinDistill\Reporting\ReportOptions;
use JinDistill\Reporting\ReportSerializer;
use JinDistill\Reporting\SchemaVersion;
use PHPUnit\Framework\TestCase;

final class ReportSerializerTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../fixtures';

    public function testItSerializesSourceOnlyAnalysis(): void
    {
        $report = (new ReportSerializer())->toArray((new JinDistiller())->analyzeFile(self::FIXTURES . '/child.jin'));

        self::assertSame(SchemaVersion::V1, $report['schema']);
        self::assertSame('analysis', $report['type']);
        self::assertSame('source-only', $report['mode']);
        self::assertContains(realpath(self::FIXTURES . '/base.jin'), array_column($report['sources'], 'path'));
        self::assertSame('file(base.jin)', $report['edges'][0]['reference']);
        self::assertArrayNotHasKey('values', $report);
        self::assertContains('/form/name', array_column($report['lineage'], 'path'));
    }

    public function testItSerializesNormalizationDiagnostics(): void
    {
        $report = (new ReportSerializer())->toArray(
            (new \JinDistill\Formatting\Normalizer(
                new \JinDistill\Analysis\Analyzer(new \JinDistill\Analysis\SourceGraphBuilder(
                    new \JinDistill\Source\MemorySourceLoader([]),
                    new \JinDistill\Decoders\JinDecoder(),
                    [new \JinDistill\Source\RelativeExtendsResolver()],
                )),
            ))->normalize('name=CPA', new \JinDistill\Source\SourceId('memory://input.jin', 'input.jin')),
        );

        self::assertSame('normalize', $report['type']);
        self::assertSame("name = CPA\n", $report['content']);
        self::assertSame('jin.style.canonical-layout', $report['diagnostics'][0]['rule']);
    }

    public function testItSerializesFlattenAndDiffResults(): void
    {
        $serializer = new ReportSerializer();

        self::assertSame('flatten', $serializer->toArray((new JinDistiller())->flattenFile(self::FIXTURES . '/child.jin'))['type']);

        $diff = $serializer->toArray((new JinDistiller())->diffFiles(
            self::FIXTURES . '/base.jin',
            self::FIXTURES . '/child.jin',
            self::FIXTURES . '/generated.jin',
            (new DiffOptions())->withVerification(true),
        ));

        self::assertSame('diff', $diff['type']);
        self::assertSame(['added', 'changed', 'removed', 'metadata-changed'], array_keys($diff['differences']));
        self::assertTrue($diff['verification']['equivalent']);
        self::assertIsArray($diff['removals']);
    }

    public function testItRedactsEvaluatedValuesByDefault(): void
    {
        $distiller = new JinDistiller();
        $options = new \JinDistill\Evaluation\EvaluationOptions([], ['hello' => static fn (string $name): string => 'secret-' . $name]);
        $analysis = $distiller->evaluateFile(self::FIXTURES . '/evaluation-functions.jin', $options);

        $redacted = (new ReportSerializer())->toArray($analysis);
        $disclosed = (new ReportSerializer())->toArray($analysis, new ReportOptions(includeValues: true));

        self::assertSame(['path' => '/greeting', 'type' => 'string'], $redacted['values'][0]);
        self::assertSame('secret-World', $disclosed['values'][0]['value']);
    }

    public function testItRejectsUnsupportedResultTypes(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ReportSerializer())->toArray(new \stdClass());
    }

    public function testItEmitsStableJson(): void
    {
        $json = (new ReportSerializer())->toJson((new JinDistiller())->analyzeFile(self::FIXTURES . '/base.jin'));

        self::assertStringStartsWith('{"schema":"1.0","type":"analysis"', $json);
        self::assertStringNotContainsString('\\/', $json);
    }
}
