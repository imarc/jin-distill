<?php

namespace JinDistill\Tests\Evaluation;

use JinDistill\Analysis\Analyzer;
use JinDistill\Analysis\SourceGraphBuilder;
use JinDistill\Evaluation\DotinkEvaluator;
use JinDistill\Evaluation\EvaluationOptions;
use JinDistill\Source\FilesystemSourceLoader;
use JinDistill\Source\FunctionExtendsResolver;
use JinDistill\Source\PathPolicy;
use JinDistill\Source\RelativeExtendsResolver;
use JinDistill\Decoders\JinDecoder;
use PHPUnit\Framework\TestCase;

final class DotinkEvaluatorTest extends TestCase
{
    public function testItExplicitlyEvaluatesAfterSafeAnalysis(): void
    {
        $fixtureRoot = realpath(__DIR__ . '/../fixtures');
        self::assertNotFalse($fixtureRoot);
        $analyzer = new Analyzer(new SourceGraphBuilder(
            new FilesystemSourceLoader(new PathPolicy([$fixtureRoot])),
            new JinDecoder(),
            [new RelativeExtendsResolver(), new FunctionExtendsResolver('file', $fixtureRoot)],
        ));
        $evaluator = new DotinkEvaluator($analyzer);

        $result = $evaluator->evaluateFile($fixtureRoot . '/base.jin');

        self::assertSame(\JinDistill\Analysis\AnalysisMode::Evaluated, $result->mode());
        self::assertSame('Default', $result->resolvedData()['form']['name']);
    }

    public function testItPassesCallerProvidedFunctionsOnlyToExplicitEvaluation(): void
    {
        $fixtureRoot = realpath(__DIR__ . '/../fixtures');
        self::assertNotFalse($fixtureRoot);
        $analyzer = new Analyzer(new SourceGraphBuilder(
            new FilesystemSourceLoader(new PathPolicy([$fixtureRoot])),
            new JinDecoder(),
            [new RelativeExtendsResolver()],
        ));
        $evaluator = new DotinkEvaluator($analyzer);

        $result = $evaluator->evaluateFile(
            $fixtureRoot . '/evaluation-functions.jin',
            new EvaluationOptions(functions: ['hello' => static fn (string $name): string => 'Hello ' . $name]),
        );

        self::assertSame('Hello World', $result->resolvedData()['greeting']);
    }
}
