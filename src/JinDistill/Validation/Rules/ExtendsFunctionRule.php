<?php

namespace JinDistill\Validation\Rules;

use JinDistill\Diagnostics\Diagnostic;
use JinDistill\Evaluation\EvaluationOptions;
use JinDistill\Syntax\Assignment;
use JinDistill\Syntax\Document;
use JinDistill\Validation\Rule;
use JinDistill\Validation\ValidationRules;

final class ExtendsFunctionRule implements Rule
{
    public function check(Document $document, ValidationRules $rules, ?EvaluationOptions $evaluation): array
    {
        if ($evaluation === null || array_key_exists('file', $evaluation->functions())) {
            return [];
        }

        $diagnostics = [];

        foreach ($document->statements() as $statement) {
            if (!$statement instanceof Assignment
                || $statement->path()->segments() !== ['--extends']
                || preg_match('/^file\s*\(/', $statement->value()->raw()) !== 1) {
                continue;
            }

            $diagnostics[] = new Diagnostic(
                'jin.style.extends-function',
                $rules->severity('jin.style.extends-function'),
                'Hiraeth file() inheritance requires a registered file evaluation function.',
                $statement->path(),
                $statement->span(),
                'Register file() before evaluation.',
            );
        }

        return $diagnostics;
    }
}
