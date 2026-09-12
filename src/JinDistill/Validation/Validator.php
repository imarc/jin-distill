<?php

namespace JinDistill\Validation;

use JinDistill\Analysis\AnalysisResult;
use JinDistill\Diagnostics\Diagnostic;
use JinDistill\Evaluation\EvaluationOptions;
use JinDistill\Validation\Rules\CanonicalLayoutRule;
use JinDistill\Validation\Rules\DuplicatePathRule;
use JinDistill\Validation\Rules\ExtendsFunctionRule;

final class Validator
{
    /** @var list<Rule> */
    private array $rules;

    /** @param list<Rule> $rules */
    public function __construct(array $rules = [])
    {
        $this->rules = $rules === []
            ? [new DuplicatePathRule(), new CanonicalLayoutRule(), new ExtendsFunctionRule()]
            : $rules;
    }

    /** @return list<Diagnostic> */
    public function validate(AnalysisResult $analysis, ?ValidationRules $rules = null, ?EvaluationOptions $evaluation = null): array
    {
        $rules ??= ValidationRules::imarcV1();
        $diagnostics = [];

        foreach ($analysis->sourceGraph()->documents() as $document) {
            foreach ($this->rules as $rule) {
                foreach ($rule->check($document, $rules, $evaluation) as $diagnostic) {
                    $diagnostics[] = $diagnostic;
                }
            }
        }

        return $diagnostics;
    }
}
