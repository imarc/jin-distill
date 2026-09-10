<?php

namespace JinDistill\Validation;

use JinDistill\Analysis\AnalysisResult;
use JinDistill\Diagnostics\Diagnostic;
use JinDistill\Diagnostics\Severity;
use JinDistill\Syntax\Assignment;

final class Validator
{
    /** @return list<Diagnostic> */
    public function validate(AnalysisResult $analysis): array
    {
        $diagnostics = [];
        foreach ($analysis->sourceGraph()->documents() as $document) {
            $seen = [];
            foreach ($document->statements() as $statement) {
                if (!$statement instanceof Assignment || str_starts_with($statement->path()->segments()[0], '--')) {
                    continue;
                }
                $path = $statement->path()->toJsonPointer();
                if (isset($seen[$path])) {
                    $diagnostics[] = new Diagnostic(
                        'jin.style.duplicate-path',
                        Severity::Error,
                        sprintf('Duplicate declaration for %s in one source file.', $path),
                        $statement->path(),
                        $statement->span(),
                        'Keep only the final declaration.',
                    );
                }
                $seen[$path] = true;
            }
        }
        return $diagnostics;
    }
}
