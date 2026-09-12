<?php

namespace JinDistill\Validation\Rules;

use JinDistill\Diagnostics\Diagnostic;
use JinDistill\Evaluation\EvaluationOptions;
use JinDistill\Syntax\Assignment;
use JinDistill\Syntax\Document;
use JinDistill\Validation\Rule;
use JinDistill\Validation\ValidationRules;

final class DuplicatePathRule implements Rule
{
    public function check(Document $document, ValidationRules $rules, ?EvaluationOptions $evaluation): array
    {
        $diagnostics = [];
        $seen = [];

        foreach ($document->statements() as $statement) {
            if (!$statement instanceof Assignment || str_starts_with($statement->path()->segments()[0], '--')) {
                continue;
            }

            $path = $statement->path()->toJsonPointer();

            if (isset($seen[$path])) {
                $diagnostics[] = new Diagnostic(
                    'jin.style.duplicate-path',
                    $rules->severity('jin.style.duplicate-path'),
                    sprintf('Duplicate declaration for %s in one source file.', $path),
                    $statement->path(),
                    $statement->span(),
                    'Keep only the final declaration.',
                );
            }

            $seen[$path] = true;
        }

        return $diagnostics;
    }
}
