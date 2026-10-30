<?php

namespace JinDistill\Tests\Diagnostics;

use JinDistill\Diagnostics\Diagnostic;
use JinDistill\Diagnostics\Severity;
use JinDistill\Source\Path;
use JinDistill\Source\SourceId;
use JinDistill\Source\SourceSpan;
use PHPUnit\Framework\TestCase;

final class DiagnosticTest extends TestCase
{
    public function testItSerializesStableSourceDetails(): void
    {
        $source = new SourceId('/app/form.jin', 'config/form.jin');
        $span = new SourceSpan($source, 3, 5, 3, 12);
        $diagnostic = new Diagnostic(
            'jin.style.duplicate-path',
            Severity::Error,
            'Path is defined twice.',
            Path::fromSegments(['form', 'first.name']),
            $span,
            'Remove one definition.'
        );

        self::assertSame([
            'rule' => 'jin.style.duplicate-path',
            'severity' => 'error',
            'message' => 'Path is defined twice.',
            'path' => '/form/first.name',
            'span' => [
                'source' => '/app/form.jin',
                'reference' => 'config/form.jin',
                'start' => ['line' => 3, 'column' => 5],
                'end' => ['line' => 3, 'column' => 12],
            ],
            'suggestion' => 'Remove one definition.',
        ], $diagnostic->toArray());
    }
}
