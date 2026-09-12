<?php

namespace JinDistill\Validation\Rules;

use JinDistill\Diagnostics\Diagnostic;
use JinDistill\Evaluation\EvaluationOptions;
use JinDistill\Formatting\FormatOptions;
use JinDistill\Formatting\JinRenderer;
use JinDistill\Source\Path;
use JinDistill\Syntax\Assignment;
use JinDistill\Syntax\Document;
use JinDistill\Syntax\Section;
use JinDistill\Syntax\Statement;
use JinDistill\Validation\Rule;
use JinDistill\Validation\ValidationRules;

/**
 * Compares every statement against its canonical rendering so noncanonical
 * indentation, quoting, trailing commas, and section references are reported
 * without rejecting the source.
 */
final class CanonicalLayoutRule implements Rule
{
    public function __construct(private ?JinRenderer $renderer = null, private ?FormatOptions $options = null)
    {
        $this->renderer ??= new JinRenderer();
        $this->options ??= new FormatOptions();
    }

    public function check(Document $document, ValidationRules $rules, ?EvaluationOptions $evaluation): array
    {
        $contents = $document->contents();

        if ($contents === null) {
            return [];
        }

        $diagnostics = [];

        if (str_contains($contents, "\r")) {
            $diagnostics[] = new Diagnostic(
                'jin.style.line-ending',
                $rules->severity('jin.style.line-ending'),
                'Jin sources use LF line endings.',
                null,
                $document->statements()[0]?->span(),
                'Rewrite the file with LF line endings.',
            );
        }

        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $contents));
        $section = [];

        foreach ($document->statements() as $statement) {
            if ($statement instanceof Section) {
                if (str_starts_with($statement->lexeme(), '&')) {
                    $diagnostics[] = new Diagnostic(
                        'jin.style.section-reference',
                        $rules->severity('jin.style.section-reference'),
                        'Relative section references are not canonical.',
                        $statement->path(),
                        $statement->span(),
                        sprintf('Rewrite as [%s].', implode('.', $statement->path()->segments())),
                    );
                    $section = $statement->path()->segments();
                    continue;
                }

                $section = $statement->path()->segments();
            }

            if (!$statement instanceof Section && !$statement instanceof Assignment) {
                continue;
            }

            $canonical = $this->canonical($statement, $section, $document);
            $original = $this->original($statement, $lines);

            if ($original === null || $original === $canonical) {
                continue;
            }

            $diagnostics[] = new Diagnostic(
                'jin.style.canonical-layout',
                $rules->severity('jin.style.canonical-layout'),
                'Statement layout is not canonical.',
                $statement instanceof Assignment ? $statement->path() : null,
                $statement->span(),
                $canonical,
            );
        }

        return $diagnostics;
    }

    /** @param list<string> $section */
    private function canonical(Statement $statement, array $section, Document $document): string
    {
        $nodes = [$statement];

        if ($statement instanceof Assignment && $section !== []) {
            $nodes = [new Section(Path::fromSegments($section), implode('.', $section), $statement->span()), $statement];
        }

        $rendered = rtrim($this->renderer->render(new Document($nodes, $document->source()), $this->options), "\n");

        return count($nodes) === 2 ? substr($rendered, strpos($rendered, "\n") + 1) : $rendered;
    }

    /** @param list<string> $lines */
    private function original(Statement $statement, array $lines): ?string
    {
        $span = $statement->span()->toArray();
        $start = $span['start']['line'] - 1;
        $length = $span['end']['line'] - $span['start']['line'] + 1;

        if (!isset($lines[$start]) || !isset($lines[$start + $length - 1])) {
            return null;
        }

        return implode("\n", array_slice($lines, $start, $length));
    }
}
