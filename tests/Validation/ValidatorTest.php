<?php

namespace JinDistill\Tests\Validation;

use JinDistill\Analysis\Analyzer;
use JinDistill\Analysis\SourceGraphBuilder;
use JinDistill\Decoders\JinDecoder;
use JinDistill\Diagnostics\Severity;
use JinDistill\Evaluation\EvaluationOptions;
use JinDistill\Source\MemorySourceLoader;
use JinDistill\Source\RelativeExtendsResolver;
use JinDistill\Source\SourceId;
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

    public function testItReportsNoncanonicalIndentationWithASuggestedFix(): void
    {
        $diagnostics = $this->validate("[form]\nname = CPA");

        self::assertSame(['jin.style.canonical-layout'], $this->ruleIds($diagnostics));
        self::assertSame("\tname = CPA", $diagnostics[0]->suggestion());
        self::assertSame(2, $diagnostics[0]->span()?->toArray()['start']['line']);
    }

    public function testItReportsNoncanonicalQuotingAndTrailingCommas(): void
    {
        self::assertSame(['jin.style.canonical-layout'], $this->ruleIds($this->validate('name = "CPA"')));
        self::assertSame(['jin.style.canonical-layout'], $this->ruleIds($this->validate("items = [\n\t\"a\"\n]")));
    }

    public function testItReportsWindowsLineEndings(): void
    {
        self::assertSame(['jin.style.line-ending'], $this->ruleIds($this->validate("name = CPA\r\n")));
    }

    public function testItReportsNothingForCanonicalSources(): void
    {
        self::assertSame([], $this->validate("; Form\nname = CPA\n\n[form]\n\tenabled = true\n"));
    }

    /** @param list<\JinDistill\Diagnostics\Diagnostic> $diagnostics @return list<string> */
    private function ruleIds(array $diagnostics): array
    {
        return array_map(static fn ($diagnostic): string => $diagnostic->ruleId(), $diagnostics);
    }

    /** @return list<\JinDistill\Diagnostics\Diagnostic> */
    private function validate(string $contents): array
    {
        $analysis = (new Analyzer(new SourceGraphBuilder(
            new MemorySourceLoader([]),
            new JinDecoder(),
            [new RelativeExtendsResolver()],
        )))->analyze($contents, new SourceId('memory://input.jin', 'input.jin'));

        return (new Validator())->validate($analysis);
    }
}
