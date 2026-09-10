<?php

namespace JinDistill\Tests\Validation;

use JinDistill\Analysis\Analyzer;
use JinDistill\Analysis\SourceGraphBuilder;
use JinDistill\Decoders\JinDecoder;
use JinDistill\Source\MemorySourceLoader;
use JinDistill\Source\RelativeExtendsResolver;
use JinDistill\Source\SourceId;
use JinDistill\Diagnostics\Severity;
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
}
