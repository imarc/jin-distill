<?php

namespace JinDistill\Formatting;

use JinDistill\Exceptions\UnrepresentableValueException;
use JinDistill\Source\Path;
use JinDistill\Syntax\Assignment;
use JinDistill\Syntax\BlankLine;
use JinDistill\Syntax\Comment;
use JinDistill\Syntax\Document;
use JinDistill\Syntax\Section;
use JinDistill\Syntax\Statement;
use JinDistill\Syntax\Value;
use JinDistill\Syntax\ValueKind;

final class JinRenderer
{
    private ExtendsRenderer $extendsRenderer;

    public function __construct(?ExtendsRenderer $extendsRenderer = null)
    {
        $this->extendsRenderer = $extendsRenderer ?? new ExtendsRenderer();
    }

    public function render(Document $document, ?FormatOptions $options = null): string
    {
        $options ??= new FormatOptions();
        $lines = array_column($this->renderStatements($document, $options), 'text');

        return implode($options->lineEnding()->value, $lines) . $options->lineEnding()->value;
    }

    /**
     * @internal Shared statement layout for rendering and validation.
     * @return list<array{statement: Statement, text: string}>
     */
    public function renderStatements(Document $document, ?FormatOptions $options = null): array
    {
        $options ??= new FormatOptions();
        $document = (new DocumentFormatter())->order($document, $options->ordering());
        $rendered = [];
        $prefixes = [];
        $section = [];

        foreach ($document->statements() as $statement) {
            if ($statement instanceof BlankLine) {
                if ($options->spacingPolicy() === SpacingPolicy::None
                    || ($options->spacingPolicy() === SpacingPolicy::One && $rendered !== [] && end($rendered)['statement'] instanceof BlankLine)) {
                    continue;
                }
                $text = '';
            } elseif ($statement instanceof Comment) {
                $text = $this->indent($section, $options) . $this->renderComment($statement->text());
            } elseif ($statement instanceof Section) {
                $section = $statement->path()->segments();
                $lexeme = $options->sectionReferences() === SectionReferenceStyle::Preserve
                    ? $statement->lexeme()
                    : implode('.', $section);
                $text = '[' . $lexeme . ']';
            } elseif ($statement instanceof Assignment) {
                $row = $this->renderAssignment($statement, $section, $document, $options);
                $text = $row['text'];
                if ($row['prefix'] !== null) {
                    $prefixes[count($rendered)] = $row['prefix'];
                }
            } else {
                continue;
            }

            $rendered[] = ['statement' => $statement, 'text' => $text];
        }

        while ($rendered !== [] && end($rendered)['statement'] instanceof BlankLine) {
            array_pop($rendered);
        }

        if ($options->assignmentAlignment()) {
            $lines = $this->align(array_column($rendered, 'text'), $prefixes);
            $aligned = [];
            foreach ($rendered as $index => $row) {
                $aligned[] = ['statement' => $row['statement'], 'text' => $lines[$index]];
            }
            return $aligned;
        }

        return $rendered;
    }

    /**
     * @param list<string> $section
     * @return array{text: string, prefix: ?string}
     */
    private function renderAssignment(Assignment $assignment, array $section, Document $document, FormatOptions $options): array
    {
        $segments = $assignment->path()->segments();
        $directive = str_starts_with($segments[0] ?? '', '--');
        $key = $this->assignmentKey($segments, $section);
        $indentation = $directive ? '' : $this->indent($section, $options);
        $value = $key === '--extends'
            ? $this->extendsRenderer->render($assignment->value(), $options)
            : $this->renderValue($assignment->value(), count($section), $assignment->path(), $document, $options);
        $inlineComment = $assignment->inlineComment();

        return [
            'text' => $indentation . $key . ' = ' . $value . ($inlineComment === null ? '' : ' ; ' . $inlineComment->text()),
            'prefix' => !$directive && $inlineComment === null && strpbrk($value, "\r\n") === false ? $indentation . $key : null,
        ];
    }

    /**
     * @param list<string> $segments
     * @param list<string> $section
     */
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
            $lexeme = $document->numericLexemes()[$path->toJsonPointer()] ?? null;
            if ($options->numericStyle() === NumericStyle::Preserve && is_string($lexeme)
                && $this->numericLexemeMatches($lexeme, $value)) {
                return $lexeme;
            }
            return (string) $value;
        }
        if (is_string($value)) {
            return $json || $options->stringQuoting() === StringQuoting::Always
                ? $this->quote($value)
                : $this->quoteStringIfNeeded($value);
        }
        if ($value instanceof \stdClass) {
            $value = get_object_vars($value);
        }
        if (!is_array($value)) {
            throw new UnrepresentableValueException($path->toJsonPointer(), $document->source()->canonicalPath(), $value);
        }
        if ($value === []) {
            return $json ? '[]' : '{}';
        }

        if (array_is_list($value)) {
            $lines = ['['];
            foreach ($value as $index => $item) {
                foreach ($this->metadataTrivia($document, $path->append((string) $index), $depth + 1, $options) as $line) {
                    $lines[] = $line;
                }
                $lines[] = $this->indentDepth($depth + 1, $options)
                    . $this->renderStaticValue($item, $depth + 1, $path->append((string) $index), $document, $options, true) . ',';
            }
            $lines[] = $this->indentDepth($depth, $options) . ']';
            return implode($options->lineEnding()->value, $lines);
        }

        $lines = ['{'];
        $prefixes = [];
        foreach ($value as $key => $item) {
            $itemPath = $path->append((string) $key);
            foreach ($this->metadataTrivia($document, $itemPath, $depth + 1, $options) as $line) {
                $lines[] = $line;
            }
            $prefix = $this->indentDepth($depth + 1, $options) . $this->quote((string) $key);
            $renderedItem = $this->renderStaticValue($item, $depth + 1, $itemPath, $document, $options, true);
            $inlineComment = $options->assignmentAlignment() ? ($this->metadata($document, $itemPath)['inlineComment'] ?? null) : null;
            $multiline = strpbrk($renderedItem, "\r\n") !== false;
            if ($multiline && is_string($inlineComment)) {
                $lines[] = $this->indentDepth($depth + 1, $options) . $this->renderComment($inlineComment);
                $inlineComment = null;
            }
            if ($inlineComment === null && !$multiline) {
                $prefixes[count($lines)] = $prefix;
            }
            $lines[] = $prefix . ': ' . $renderedItem . ',' . (is_string($inlineComment) ? ' ; ' . $inlineComment : '');
        }
        $lines[] = $this->indentDepth($depth, $options) . '}';
        if ($options->assignmentAlignment()) {
            $lines = $this->align($lines, $prefixes, 1);
        }
        return implode($options->lineEnding()->value, $lines);
    }

    /**
     * @param list<string> $lines
     * @param array<int, string> $prefixes Eligible row prefixes ending at the key boundary.
     * @return list<string>
     */
    private function align(array $lines, array $prefixes, int $minimumPadding = 0): array
    {
        $aligned = [];
        $start = 0;
        while ($start < count($lines)) {
            if (!isset($prefixes[$start])) {
                $aligned[] = $lines[$start];
                $start++;
                continue;
            }

            $end = $start;
            $width = strlen($prefixes[$start]);
            while (isset($prefixes[$end + 1])) {
                $end++;
                $width = max($width, strlen($prefixes[$end]));
            }

            for ($index = $start; $index <= $end; $index++) {
                $length = strlen($prefixes[$index]);
                $aligned[] = substr_replace($lines[$index], str_repeat(' ', $width - $length + $minimumPadding), $length, 0);
            }
            $start = $end + 1;
        }

        return $aligned;
    }

    private function numericLexemeMatches(string $lexeme, int|float $value): bool
    {
        if (preg_match('/^0x[0-9a-f]+$/i', $lexeme) === 1) {
            return hexdec(substr($lexeme, 2)) === $value;
        }
        if (preg_match('/^0b[01]+$/i', $lexeme) === 1) {
            return bindec(substr($lexeme, 2)) === $value;
        }
        if (preg_match('/^0[0-7]+$/', $lexeme) === 1) {
            return octdec($lexeme) === $value;
        }
        return is_numeric($lexeme) && (str_contains($lexeme, '.') || stripos($lexeme, 'e') !== false
            ? (float) $lexeme === $value
            : (int) $lexeme === $value);
    }

    /** @param list<string> $lines */
    private function appendBlankLines(array &$lines, int $count, FormatOptions $options): void
    {
        if ($count === 0 || $options->spacingPolicy() === SpacingPolicy::None) {
            return;
        }

        foreach (range(1, $options->spacingPolicy() === SpacingPolicy::One ? 1 : $count) as $_) {
            $lines[] = '';
        }
    }

    /** @return array<mixed> */
    private function metadata(Document $document, Path $path): array
    {
        $metadata = $document->metadata[$path->toJsonPointer()]
            ?? $document->metadata[implode('.', $path->segments())]
            ?? null;

        return is_array($metadata) ? $metadata : [];
    }

    /** @return list<string> */
    private function metadataTrivia(Document $document, Path $path, int $depth, FormatOptions $options): array
    {
        $trivia = $this->metadata($document, $path)['leadingTrivia'] ?? [];
        if (!is_array($trivia)) {
            return [];
        }

        $lines = [];
        $blankLines = 0;
        foreach ($trivia as $item) {
            if (!is_array($item) || !is_string($item['type'] ?? null)) {
                continue;
            }
            if ($item['type'] === 'blank') {
                $blankLines++;
                continue;
            }
            $this->appendBlankLines($lines, $blankLines, $options);
            $blankLines = 0;
            if ($item['type'] === 'comment' && is_string($item['text'] ?? null)) {
                $lines[] = $this->indentDepth($depth, $options) . $this->renderComment($item['text']);
            }
        }
        $this->appendBlankLines($lines, $blankLines, $options);

        return $lines;
    }

    private function renderComment(string $text): string
    {
        return ';' . ($text === '' ? '' : ' ' . $text);
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
