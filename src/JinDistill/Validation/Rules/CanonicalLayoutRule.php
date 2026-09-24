<?php

namespace JinDistill\Validation\Rules;

use JinDistill\Diagnostics\Diagnostic;
use JinDistill\Evaluation\EvaluationOptions;
use JinDistill\Formatting\DocumentFormatter;
use JinDistill\Formatting\FormatOptions;
use JinDistill\Formatting\JinRenderer;
use JinDistill\Formatting\LineEnding;
use JinDistill\Formatting\SectionReferenceStyle;
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
    private JinRenderer $renderer;
    private FormatOptions $options;

    public function __construct(?JinRenderer $renderer = null, ?FormatOptions $options = null)
    {
        $this->renderer = $renderer ?? new JinRenderer();
        $this->options = $options ?? new FormatOptions();
    }

    public function check(Document $document, ValidationRules $rules, ?EvaluationOptions $evaluation): array
    {
        $contents = $document->contents();

        if ($contents === null) {
            return [];
        }

        $diagnostics = [];

        $wrongEnding = $this->options->lineEnding() === LineEnding::Lf
            ? str_contains($contents, "\r")
            : preg_match('/(?<!\r)\n|\r(?!\n)/', $contents) === 1;
        if ($wrongEnding) {
            $diagnostics[] = new Diagnostic(
                'jin.style.line-ending',
                $rules->severity('jin.style.line-ending'),
                sprintf('Jin source uses noncanonical line endings; expected %s.', $this->options->lineEnding()->name),
                null,
                $document->statements()[0]?->span(),
                sprintf('Rewrite the file with %s line endings.', $this->options->lineEnding()->name),
            );
        }

        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $contents));
        $section = [];

        foreach ($document->statements() as $statement) {
            if ($statement instanceof Section) {
                if ($this->options->sectionReferences() === SectionReferenceStyle::Explicit && str_starts_with($statement->lexeme(), '&')) {
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

            if ($original === null || $original === str_replace("\r\n", "\n", $canonical)) {
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

        $ordered = (new DocumentFormatter())->order($document, $this->options->ordering());
        if ($this->statementOrder($document) !== $this->statementOrder($ordered)) {
            $diagnostics[] = new Diagnostic(
                'jin.style.canonical-layout',
                $rules->severity('jin.style.canonical-layout'),
                'Statement order is not canonical.',
                null,
                $document->statements()[0]?->span(),
                $this->renderer->render($document, $this->options),
            );
        }

        return $diagnostics;
    }

    /** @return list<string> */
    private function statementOrder(Document $document): array
    {
        $paths = [];
        foreach ($document->statements() as $statement) {
            if ($statement instanceof Section || $statement instanceof Assignment) {
                $paths[] = $statement::class . ':' . $statement->path()->toJsonPointer();
            }
        }
        return $paths;
    }

    /** @param list<string> $section */
    private function canonical(Statement $statement, array $section, Document $document): string
    {
        $nodes = [$statement];

        if ($statement instanceof Assignment && $section !== []) {
            $nodes = [new Section(Path::fromSegments($section), implode('.', $section), $statement->span()), $statement];
        }

        $rendered = rtrim($this->renderer->render(new Document($nodes, $document->source(), [], [], $document->metadata, null, $document->numericLexemes()), $this->options), "\r\n");

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
