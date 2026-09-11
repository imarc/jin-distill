<?php

namespace JinDistill\Tests;

use JinDistill\JinDistiller;
use PHPUnit\Framework\TestCase;

final class JinDistillerTest extends TestCase
{
    public function testItNormalizesWithoutResolvingExtends(): void
    {
        $output = (new JinDistiller())->normalizeFile(__DIR__ . '/fixtures/child.jin');

        self::assertStringContainsString('--extends = file(base.jin)', $output);
        self::assertStringContainsString('--without = [', $output);
        self::assertStringContainsString('"birthDate": true,', $output);
        self::assertStringContainsString("\tname = CPA\n", $output);
    }

    public function testItFlattensToStandaloneJin(): void
    {
        $output = (new JinDistiller())->flattenFile(__DIR__ . '/fixtures/child.jin');

        self::assertStringNotContainsString('--extends', $output);
        self::assertStringNotContainsString('--without', $output);
        self::assertStringContainsString('name = CPA', $output);
        self::assertStringContainsString('"firstName": true,', $output);
        self::assertStringNotContainsString('"avatar"', $output);
        self::assertStringContainsString('"birthDate": true,', $output);
    }

    public function testItExposesExplicitSafeAnalysisAndEvaluationWorkflows(): void
    {
        $path = __DIR__ . '/fixtures/base.jin';
        $distiller = new JinDistiller();

        self::assertSame(
            \JinDistill\Analysis\AnalysisMode::SourceOnly,
            $distiller->analyzeFile($path)->mode(),
        );
        self::assertSame('Default', $distiller->evaluateFile($path)->resolvedData()['form']['name']);
    }

    public function testItExposesOptInSemanticVerification(): void
    {
        $result = (new JinDistiller())->verifySemantics('name = CPA', 'name=CPA');

        self::assertTrue($result->isEquivalent());
    }

    public function testItGeneratesDiffContentWithoutWritingOutput(): void
    {
        $output = __DIR__ . '/fixtures/generated-diff.jin';
        $result = (new JinDistiller())->diffFiles(__DIR__ . '/fixtures/base.jin', __DIR__ . '/fixtures/child.jin', $output);

        self::assertStringContainsString('--extends = file(base.jin)', $result->content());
        self::assertFileDoesNotExist($output);
    }
}
