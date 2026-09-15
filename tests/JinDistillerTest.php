<?php

namespace JinDistill\Tests;

use Dotink\Jin\Parser;
use JinDistill\Evaluation\JinEvaluator;
use JinDistill\JinDistiller;
use PHPUnit\Framework\TestCase;

final class JinDistillerTest extends TestCase
{
    public function testItNormalizesWithoutResolvingExtends(): void
    {
        $output = (new JinDistiller())->normalizeFile(__DIR__ . '/fixtures/child.jin')->content();

        self::assertStringContainsString('--extends = file(base.jin)', $output);
        self::assertStringContainsString('--without = [', $output);
        self::assertStringContainsString('"birthDate": true,', $output);
        self::assertStringContainsString("\tname = CPA\n", $output);
    }

    public function testItFlattensToStandaloneJin(): void
    {
        $output = (new JinDistiller())->flattenFile(__DIR__ . '/fixtures/child.jin')->content();

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

    public function testItUsesImmutableApplicationAndAllowedRootsForFileInheritance(): void
    {
        $testsRoot = __DIR__;
        $distiller = new JinDistiller();
        $configured = $distiller
            ->withApplicationRoot($testsRoot)
            ->withAllowedRoots([$testsRoot . '/fixtures']);

        self::assertNotSame($distiller, $configured);
        self::assertStringContainsString(
            'name = Default',
            $configured->flattenFile($testsRoot . '/fixtures/rooted-child.jin')->content(),
        );
    }

    public function testItUsesAnImmutableCallerProvidedEvaluatorForRuntimeWorkflows(): void
    {
        $evaluator = new JinEvaluator(static fn (): Parser => new Parser([], [
            'hello' => static fn (string $name): string => 'Runtime ' . $name,
        ]));
        $distiller = new JinDistiller();
        $configured = $distiller->withEvaluator($evaluator);

        self::assertNotSame($distiller, $configured);
        self::assertSame(
            'Runtime World',
            $configured->evaluateFile(__DIR__ . '/fixtures/evaluation-functions.jin')->resolvedData()['greeting'],
        );
        self::assertTrue($configured->verifySemantics('greeting = hello(World)', 'greeting=hello(World)')->isEquivalent());
    }

    public function testStaticWorkflowsNeverInvokeTheRuntimeEvaluator(): void
    {
        $evaluator = new JinEvaluator(static function (): Parser {
            throw new \RuntimeException('Runtime evaluator invoked.');
        });

        $output = (new JinDistiller())
            ->withEvaluator($evaluator)
            ->flattenFile(__DIR__ . '/fixtures/child.jin')
            ->content();

        self::assertStringContainsString('name = CPA', $output);
    }

    public function testItVerifiesAFileAgainstGeneratedContentWithRuntimeInheritance(): void
    {
        $fixtureRoot = realpath(__DIR__ . '/fixtures');
        self::assertNotFalse($fixtureRoot);
        $path = $fixtureRoot . '/child.jin';
        $evaluator = new JinEvaluator(static fn (): Parser => new Parser([], [
            'file' => static fn (string $file): string => $fixtureRoot . '/' . $file,
        ]));
        $distiller = (new JinDistiller())->withEvaluator($evaluator);

        $result = $distiller->verifyFileSemantics($path, $distiller->flattenFile($path)->content());

        self::assertTrue($result->isEquivalent());
    }
}
