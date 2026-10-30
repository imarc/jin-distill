<?php

namespace JinDistill\Validation;

use JinDistill\Diagnostics\Diagnostic;
use JinDistill\Evaluation\EvaluationOptions;
use JinDistill\Syntax\Document;

interface Rule
{
    /** @return list<Diagnostic> */
    public function check(Document $document, ValidationRules $rules, ?EvaluationOptions $evaluation): array;
}
