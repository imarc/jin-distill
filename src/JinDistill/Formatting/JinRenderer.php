<?php

namespace JinDistill\Formatting;

use JinDistill\Exceptions\UnrepresentableValueException;
use JinDistill\Source\Path;
use JinDistill\Syntax\Assignment;
use JinDistill\Syntax\BlankLine;
use JinDistill\Syntax\Comment;
use JinDistill\Syntax\Document;
use JinDistill\Syntax\Section;
use JinDistill\Syntax\Value;
use JinDistill\Syntax\ValueKind;

final class JinRenderer
{
    public function __construct(private ?ExtendsRenderer $extendsRenderer = null)
    {
        $this->extendsRenderer ??= new ExtendsRenderer();
    }

    public function render(Document $document, ?FormatOptions $options = null): string
    {
        $options ??= new FormatOptions();
        $lines = [];
        $section = [];

        foreach ($document->statements() as $statement) {
            if ($statement instanceof BlankLine) {
                $lines[] = '';
                continue;
            }

            if ($statement instanceof Comment) {
                $lines[] = $this->indent($section, $options) . '; ' . $statement->text();
                continue;
            }

            if ($statement instanceof Section) {
                $section = $statement->path()->segments();
                $lines[] = '[' . implode('.', $section) . ']';
                continue;
            }

            if ($statement instanceof Assignment) {
                $lines[] = $this->renderAssignment($statement, $section, $document, $options);
            }
        }

        while ($lines !== [] && end($lines) === '') {
            array_pop($lines);
        }

        return implode($options->lineEnding(), $lines) . $options->lineEnding();
    }

    /** @param list<string> $section */
    private function renderAssignment(Assignment $assignment, array $section, Document $document, FormatOptions $options): string
    {
        $segments = $assignment->path()->segments();
        $directive = str_starts_with($segments[0] ?? '', '--');
        $key = $this->assignmentKey($segments, $section);
        $indentation = $directive ? '' : $this->indent($section, $options);
        $value = $key === '--extends'
            ? $this->extendsRenderer->render($assignment->value(), $options)
            : $this->renderValue($assignment->value(), count($section), $assignment->path(), $document, $options);
        $inlineComment = $assignment->inlineComment();

        return $indentation . $key . ' = ' . $value . ($inlineComment === null ? '' : ' ; ' . $inlineComment->text());
    }

    /** @param list<string> $segments @param list<string> $section */
    private function assignmentKey(array $segments, array $section): string
    {
        if ($section !== [] && array_slice($segments, 0, count($section)) === $section) {
            return implode('.', array_slice($segments, count($section)));
        }

        return implode('.', $segments);
    }

    private function renderValue(Value $value, int $depth, Path $path, Document $document, FormatOptions $options): string
    {
        if (!$value->isStaticallyKnown() || $value->kind() === ValueKind::Multiline) {
            return $value->raw();
        }

        return $this->renderStaticValue(
            $value->staticValue(),
            $depth,
            $path,
            $document,
            $options,
            $value->kind() === ValueKind::Json,
        );
    }

    private function renderStaticValue(mixed $value, int $depth, Path $path, Document $document, FormatOptions $options, bool $json = false): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_string($value)) {
            return $json ? $this->quote($value) : $this->quoteStringIfNeeded($value);
        }
        if ($value instanceof \stdClass) {
            $value = get_object_vars($value);
        }
        if (!is_array($value)) {
            throw new UnrepresentableValueException($path->toJsonPointer(), $document->source()->canonicalPath(), $value);
        }
        if ($value === []) {
            return array_is_list($value) ? '[]' : '{}';
        }

        if (array_is_list($value)) {
            $lines = ['['];
            foreach ($value as $item) {
                $lines[] = $this->indentDepth($depth + 1, $options)
                    . $this->renderStaticValue($item, $depth + 1, $path, $document, $options, true) . ',';
            }
            $lines[] = $this->indentDepth($depth, $options) . ']';
            return implode($options->lineEnding(), $lines);
        }

        $lines = ['{'];
        foreach ($value as $key => $item) {
                $lines[] = $this->indentDepth($depth + 1, $options)
                . $this->quote((string) $key)
                . ': ' . $this->renderStaticValue($item, $depth + 1, $path->append((string) $key), $document, $options, true) . ',';
        }
        $lines[] = $this->indentDepth($depth, $options) . '}';
        return implode($options->lineEnding(), $lines);
    }

    /** @param list<string> $section */
    private function indent(array $section, FormatOptions $options): string
    {
        return $this->indentDepth($section === [] ? 0 : 1, $options);
    }

    private function indentDepth(int $depth, FormatOptions $options): string
    {
        return str_repeat($options->indentation(), $depth);
    }

    private function quoteStringIfNeeded(string $value): string
    {
        if ($value !== ''
            && trim($value) === $value
            && preg_match('/[;\n\r{}\[\]"\\\\]/', $value) !== 1
            && !in_array(strtolower($value), ['true', 'false', 'null'], true)
            && !is_numeric($value)) {
            return $value;
        }

        return $this->quote($value);
    }

    /** Jin escapes a quote by doubling it; backslashes stay literal. */
    private function quote(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }
}
