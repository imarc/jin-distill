<?php

namespace JinDistill\Tests\Evaluation;

use Dotink\Jin\Parser;
use JinDistill\Evaluation\EvaluationOptions;
use JinDistill\Evaluation\JinEvaluator;
use JinDistill\Evaluation\SemanticVerifier;
use PHPUnit\Framework\TestCase;

final class SemanticVerifierTest extends TestCase
{
    public function testItReportsEquivalentDotinkValuesForTrustedInputs(): void
    {
        $result = (new SemanticVerifier())->verify(
            'name = CPA' . "\n" . 'enabled = true',
            'name=CPA' . "\n" . 'enabled=true',
            new EvaluationOptions(),
        );

        self::assertTrue($result->isEquivalent());
        self::assertSame([], $result->differences());
    }

    public function testItReportsDifferencesWithoutExposingRawValues(): void
    {
        $result = (new SemanticVerifier())->verify(
            'name = CPA',
            'name = Different',
            new EvaluationOptions(),
        );

        self::assertFalse($result->isEquivalent());
        self::assertSame(['/name'], $result->differences());
    }

    public function testItIgnoresInheritanceDirectivesLeftInResolvedData(): void
    {
        $result = (new SemanticVerifier())->verify(
            'name = CPA',
            "--without = []\nname = CPA",
            new EvaluationOptions(),
        );

        self::assertSame([], $result->differences());
    }

    public function testItUsesFreshRuntimeParsersForBothInputs(): void
    {
        $evaluator = new JinEvaluator(static function (): Parser {
            $calls = 0;

            return new Parser([], ['serial' => static function () use (&$calls): int {
                return ++$calls;
            }]);
        });

        $result = (new SemanticVerifier($evaluator))->verify('value = serial()', 'value = serial()');

        self::assertTrue($result->isEquivalent());
    }
}
