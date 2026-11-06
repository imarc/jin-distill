<?php

namespace JinDistill\Tests\Validation;

use JinDistill\Analysis\Analyzer;
use JinDistill\Analysis\SourceGraphBuilder;
use JinDistill\Decoders\JinDecoder;
use JinDistill\Diagnostics\Severity;
use JinDistill\Evaluation\EvaluationOptions;
use JinDistill\Formatting\FormatOptions;
use JinDistill\Formatting\JinRenderer;
use JinDistill\Formatting\LineEnding;
use JinDistill\Formatting\OrderingPolicy;
use JinDistill\Formatting\SectionReferenceStyle;
use JinDistill\Formatting\SpacingPolicy;
use JinDistill\Formatting\StringQuoting;
use JinDistill\Source\MemorySourceLoader;
use JinDistill\Source\RelativeExtendsResolver;
use JinDistill\Source\SourceId;
use JinDistill\Validation\ValidationRules;
use JinDistill\Validation\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testDefaultLayoutSuggestionsKeepExpandingDottedAssignments(): void
    {
        foreach ([
            ["a.b = value\n", "[a]\n\tb = value", "[a]\n\tb = value\n"],
            ["[form]\n\tfield.name = value\n", "[form.field]\n\tname = value", "[form]\n[form.field]\n\tname = value\n"],
        ] as [$source, $statementSuggestion, $orderSuggestion]) {
            $diagnostics = $this->validate($source);

            self::assertSame(['jin.style.canonical-layout', 'jin.style.canonical-layout'], $this->ruleIds($diagnostics));
            self::assertSame($statementSuggestion, $diagnostics[0]->suggestion());
            self::assertSame($orderSuggestion, $diagnostics[1]->suggestion());
        }
    }

    public function testAlignedOutputIsCanonicalAcrossFormattingPolicies(): void
    {
        $source = "x=1\n\nlong=2\nobject={\"a\":1,\"long\":2}\n[beta]\nx=3\nlong=4\n[alpha]\ny=5\n[beta]\nz=6";
        $analyzer = new Analyzer(new SourceGraphBuilder(new MemorySourceLoader([]), new JinDecoder(), [new RelativeExtendsResolver()]));
        $sourceId = new SourceId('memory://input.jin', 'input.jin');

        foreach (OrderingPolicy::cases() as $ordering) {
            foreach (SpacingPolicy::cases() as $spacing) {
                foreach (LineEnding::cases() as $ending) {
                    $options = (new FormatOptions())->withAssignmentAlignment(true)
                        ->withOrdering($ordering)->withSpacingPolicy($spacing)->withLineEnding($ending);
                    $canonical = (new JinRenderer())->render((new JinDecoder())->decode($source), $options);

                    self::assertSame([], (new Validator(options: $options))->validate($analyzer->analyze($canonical, $sourceId)));
                }
            }
        }
    }

    public function testAlignmentSuggestionsKeepNeighborWidthsAndDuplicateStatementSpans(): void
    {
        $source = "[form]\n\tx = 1\n\tlong = 2\n\tx = 3";
        $analysis = (new Analyzer(new SourceGraphBuilder(new MemorySourceLoader([]), new JinDecoder(), [new RelativeExtendsResolver()])))
            ->analyze($source, new SourceId('memory://input.jin', 'input.jin'));
        $diagnostics = (new Validator(options: (new FormatOptions())->withAssignmentAlignment(true)))->validate($analysis);

        self::assertSame(['jin.style.duplicate-path', 'jin.style.canonical-layout', 'jin.style.canonical-layout'], $this->ruleIds($diagnostics));
        self::assertSame("\tx    = 1", $diagnostics[1]->suggestion());
        self::assertSame("\tx    = 3", $diagnostics[2]->suggestion());
        self::assertSame(2, $diagnostics[1]->span()?->startLine());
        self::assertSame(4, $diagnostics[2]->span()?->startLine());
    }

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

    public function testCanonicalSuggestionKeepsJsonMemberComments(): void
    {
        $diagnostics = $this->validate("fields = {\n  ; Important\n  \"name\": true\n}");

        self::assertSame(['jin.style.canonical-layout'], $this->ruleIds($diagnostics));
        self::assertStringContainsString("\t; Important\n\t\"name\": true,", $diagnostics[0]->suggestion());
    }

    public function testItReportsWindowsLineEndings(): void
    {
        self::assertSame(['jin.style.line-ending'], $this->ruleIds($this->validate("name = CPA\r\n")));
    }

    public function testItReportsNothingForCanonicalSources(): void
    {
        self::assertSame([], $this->validate("; Form\nname = CPA\n\n[form]\n\tenabled = true\n"));
    }

    public function testCanonicalDiagnosticsUseSelectedFormattingOptions(): void
    {
        $analysis = (new Analyzer(new SourceGraphBuilder(
            new MemorySourceLoader([]),
            new JinDecoder(),
            [new RelativeExtendsResolver()],
        )))->analyze("name = \"CPA\"\r\n[form]\r\n[&.fields]\r\n\tlabel = \"Text\"\r\n", new SourceId('memory://input.jin', 'input.jin'));
        $options = (new FormatOptions())
            ->withLineEnding(LineEnding::CrLf)
            ->withSectionReferences(SectionReferenceStyle::Preserve)
            ->withStringQuoting(StringQuoting::Always);

        self::assertSame([], (new Validator(options: $options))->validate($analysis));
    }

    public function testCanonicalOrderingDiagnosticsDependOnSelectedProfile(): void
    {
        $analysis = (new Analyzer(new SourceGraphBuilder(
            new MemorySourceLoader([]),
            new JinDecoder(),
            [new RelativeExtendsResolver()],
        )))->analyze("[beta]\n\tb = 2\n[alpha]\n\ta = 1\n[beta]\n\tc = 3\n", new SourceId('memory://input.jin', 'input.jin'));

        self::assertSame(['jin.style.canonical-layout'], $this->ruleIds((new Validator())->validate($analysis)));
        self::assertSame([], (new Validator(options: (new FormatOptions())->withOrdering(OrderingPolicy::SourceOrder)))->validate($analysis));
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
