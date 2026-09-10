<?php

namespace JinDistill\Tests\Validation;

use JinDistill\Analysis\Analyzer;
use JinDistill\Analysis\SourceGraphBuilder;
use JinDistill\Decoders\JinDecoder;
use JinDistill\Source\MemorySourceLoader;
use JinDistill\Source\RelativeExtendsResolver;
use JinDistill\Source\SourceId;
use JinDistill\Diagnostics\Severity;
use JinDistill\Evaluation\EvaluationOptions;
use JinDistill\Validation\ValidationRules;
use JinDistill\Validation\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testItReportsSameFileDuplicatePathsWithoutRejectingInheritanceOverrides(): void
    {
        $analyzer = new Analyzer(new SourceGraphBuilder(new MemorySourceLoader([]), new JinDecoder(), [new RelativeExtendsResolver()]));
        $analysis = $analyzer->analyze("name = first\nname = second", new SourceId('memory://input.jin', 'input.jin'));

        $diagnostics = (new Validator())->validate($analysis);

        self::assertSame('jin.style.duplicate-path', $diagnostics[0]->toArray()['rule']);
        self::assertSame('error', $diagnostics[0]->toArray()['severity']);
        self::assertSame('/name', $diagnostics[0]->toArray()['path']);
        self::assertCount(1, $diagnostics);
    }

    public function testItAllowsDuplicatePathSeverityToBeConfigured(): void
    {
        $analyzer = new Analyzer(new SourceGraphBuilder(new MemorySourceLoader([]), new JinDecoder(), [new RelativeExtendsResolver()]));
        $analysis = $analyzer->analyze("name = first\nname = second", new SourceId('memory://input.jin', 'input.jin'));

        $diagnostics = (new Validator())->validate(
            $analysis,
            ValidationRules::imarcV1()->withSeverity('jin.style.duplicate-path', Severity::Warning),
        );

        self::assertSame('warning', $diagnostics[0]->toArray()['severity']);
    }

    public function testItReportsRelativeSectionReferencesAsNoncanonical(): void
    {
        $analyzer = new Analyzer(new SourceGraphBuilder(new MemorySourceLoader([]), new JinDecoder(), [new RelativeExtendsResolver()]));
        $analysis = $analyzer->analyze("[form]\n[&.fields]\nname = CPA", new SourceId('memory://input.jin', 'input.jin'));

        $diagnostics = (new Validator())->validate($analysis);

        self::assertSame('jin.style.section-reference', $diagnostics[0]->toArray()['rule']);
        self::assertSame('/form/fields', $diagnostics[0]->toArray()['path']);
        self::assertSame('Rewrite as [form.fields].', $diagnostics[0]->toArray()['suggestion']);
    }

    public function testItReportsFileExtendsWhenEvaluationDoesNotRegisterFile(): void
    {
        $analyzer = new Analyzer(new SourceGraphBuilder(new MemorySourceLoader([]), new JinDecoder(), []));
        $analysis = $analyzer->analyze('--extends = file(base.jin)', new SourceId('memory://input.jin', 'input.jin'));

        self::assertSame(
            'jin.style.extends-function',
            (new Validator())->validate($analysis, evaluation: new EvaluationOptions())[0]->toArray()['rule'],
        );
        self::assertSame(
            [],
            (new Validator())->validate($analysis, evaluation: new EvaluationOptions(functions: ['file' => static fn (): string => 'base.jin'])),
        );
    }
}
