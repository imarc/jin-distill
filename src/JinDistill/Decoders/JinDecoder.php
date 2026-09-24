<?php

namespace JinDistill\Decoders;

use JinDistill\Diagnostics\Diagnostic;
use JinDistill\Diagnostics\Severity;
use JinDistill\Exceptions\InvalidStructureException;
use JinDistill\Source\Path;
use JinDistill\Source\SourceId;
use JinDistill\Source\SourceSpan;
use JinDistill\Support\Arr;
use JinDistill\Syntax\Assignment;
use JinDistill\Syntax\BlankLine;
use JinDistill\Syntax\Comment;
use JinDistill\Syntax\Document;
use JinDistill\Syntax\Section;
use JinDistill\Syntax\Value;
use JinDistill\Syntax\ValueKind;

final class JinDecoder implements DecoderInterface
{
    /** @var list<Diagnostic> */
    private array $diagnostics = [];

    public function __construct(protected bool $strict = true)
    {
    }

    public function decode(string $contents, SourceId|string|null $source = null): Document
    {
        $this->diagnostics = [];
        $original = $contents;
        $contents = str_replace(["\r\n", "\r"], "\n", $contents);
        $source = $source instanceof SourceId ? $source : new SourceId($source ?? 'memory://jin', $source ?? 'memory://jin');
        $lines = explode("\n", $contents);
        $statements = [];
        $data = [];
        $directives = [];
        $metadata = [];
        $numericLexemes = [];
        $section = [];
        $sectionStack = [];
        $pendingComments = [];
        $pendingTrivia = [];

        for ($index = 0; $index < count($lines); $index++) {
            $line = $lines[$index];
            $lineNumber = $index + 1;
            $trimmed = trim($line);

            if ($trimmed === '') {
                $statements[] = new BlankLine($this->span($source, $lineNumber, 1, $lineNumber, 1));
                $pendingTrivia[] = $statements[array_key_last($statements)];
                continue;
            }

            if (str_starts_with(ltrim($line), ';')) {
                $comment = new Comment(trim(substr(ltrim($line), 1)), $this->span($source, $lineNumber, 1, $lineNumber, strlen($line)));
                $statements[] = $comment;
                $pendingComments[] = $comment;
                $pendingTrivia[] = $comment;
                continue;
            }

            if (preg_match('/^\s*\[([^\]]+)\]\s*(?:;.*)?$/', $line, $matches) === 1) {
                $lexeme = trim($matches[1]);
                $section = $this->resolveSection($lexeme, $sectionStack);
                $statements[] = new Section(Path::fromSegments($section), $lexeme, $this->span($source, $lineNumber, 1, $lineNumber, strlen($line)));
                $metadata[implode('.', $section)] = [
                    'leadingComments' => array_map(static fn (Comment $comment): string => $comment->text(), $pendingComments),
                    'inlineComment' => null,
                    'line' => $lineNumber,
                    'source' => $source->canonicalPath(),
                ];
                $pendingComments = [];
                $pendingTrivia = [];
                continue;
            }

            if (preg_match('/^\s*([A-Za-z0-9_.:\\-]+|--[A-Za-z0-9_.:\\-]+)\s*=\s*(.*)$/', $line, $matches) !== 1) {
                $this->fail(sprintf('Malformed Jin line %d: %s', $lineNumber, $line), 'jin.syntax.assignment');
                continue;
            }

            $key = $matches[1];
            $rawValue = $matches[2];
            $endIndex = $index;
            while ($endIndex + 1 < count($lines) && $this->continuesValue($rawValue, $lines[$endIndex + 1])) {
                $endIndex++;
                $rawValue .= "\n" . $lines[$endIndex];
            }
            $index = $endIndex;

            if (str_starts_with($key, '--') && $section !== []) {
                $this->fail(sprintf('File-level directive %s cannot appear inside a section.', $key));
                continue;
            }

            [$valueSource, $inlineText] = $this->splitInlineComment($rawValue);
            $value = $this->parseValue($valueSource);
            $path = Path::fromSegments(array_merge($section, explode('.', $key)));
            $inlineComment = $inlineText === null ? null : new Comment($inlineText, $this->span($source, $lineNumber, 1, $lineNumber, strlen($line)));
            $assignment = new Assignment($path, $value, $pendingComments, $inlineComment, $this->span($source, $lineNumber, 1, $endIndex + 1, strlen($lines[$endIndex])), $pendingTrivia);
            $statements[] = $assignment;
            $listTrivia = [];
            if ($value->isStaticallyKnown() && $value->kind() !== ValueKind::Multiline) {
                $collector = new NumericLexemeCollector();
                $numericLexemes = array_replace($numericLexemes, $collector->collect($value->raw(), $path));
                $listTrivia = $collector->listTrivia();
            }
            $this->recordLegacyData($assignment, $key, $data, $directives, $metadata);
            foreach ($listTrivia as $pointer => $trivia) {
                $metadata[$pointer] = $trivia;
            }
            $pendingComments = [];
            $pendingTrivia = [];
        }

        $document = new Document($statements, $source, $data, $this->stringKeyed($directives), $this->stringKeyed($metadata), $original, $numericLexemes);
        if ($this->diagnostics !== []) {
            throw new InvalidStructureException('Invalid Jin structure.', $this->diagnostics, $document);
        }

        return $document;
    }

    /**
     * @param array<array-key, mixed> $values
     * @return array<string, mixed>
     */
    private function stringKeyed(array $values): array
    {
        $keyed = [];

        foreach ($values as $key => $value) {
            $keyed[(string) $key] = $value;
        }

        return $keyed;
    }

    public function decodeFile(string $path): Document
    {
        if (!is_readable($path) || ($contents = file_get_contents($path)) === false) {
            throw new InvalidStructureException(sprintf('Cannot read Jin file: %s', $path));
        }

        return $this->decode($contents, new SourceId($path, $path));
    }

    /**
     * @param list<array{int, list<string>}> $stack
     * @return list<string>
     */
    private function resolveSection(string $lexeme, array &$stack): array
    {
        if (preg_match('/^(&+)\.(.+)$/', $lexeme, $matches) === 1) {
            $references = strlen($matches[1]);
            foreach (array_reverse($stack) as [$count, $path]) {
                if ($count < $references) {
                    $resolved = [...$path, ...explode('.', $matches[2])];
                    $stack[] = [$references, $resolved];
                    return $resolved;
                }
            }
            $this->fail(sprintf('Invalid section reference: %s', $lexeme), 'jin.syntax.section-reference');
            return [];
        }

        $resolved = explode('.', $lexeme);
        $stack[] = [0, $resolved];
        return $resolved;
    }

    private function continuesValue(string $rawValue, string $nextLine): bool
    {
        if (!$this->isBalanced($rawValue)) {
            return true;
        }
        if ($this->startsStructuredValue($rawValue)) {
            return false;
        }

        $trimmed = trim($nextLine);
        return $trimmed !== ''
            && !str_starts_with($trimmed, ';')
            && preg_match('/^\[[^\]]+\]\s*(?:;.*)?$/', $trimmed) !== 1
            && preg_match('/^(?:[A-Za-z0-9_.:\\-]+|--[A-Za-z0-9_.:\\-]+)\s*=/', $trimmed) !== 1;
    }

    private function startsStructuredValue(string $value): bool
    {
        $value = ltrim($value);
        return $value !== '' && (in_array($value[0], ['{', '['], true) || preg_match('/^[a-z]+\s*\(.*\)\s*\{/s', $value) === 1);
    }

    private function isBalanced(string $value): bool
    {
        $stack = [];
        $inQuote = false;
        for ($index = 0; $index < strlen($value); $index++) {
            $character = $value[$index];
            if ($character === '"') {
                if ($inQuote && ($value[$index + 1] ?? null) === '"') {
                    $index++;
                    continue;
                }
                $inQuote = !$inQuote;
                continue;
            }
            if (!$inQuote && in_array($character, ['{', '['], true)) {
                $stack[] = $character;
            } elseif (!$inQuote && in_array($character, ['}', ']'], true)) {
                array_pop($stack);
            }
        }
        return !$inQuote && $stack === [];
    }

    /** @return array{string, ?string} */
    private function splitInlineComment(string $value): array
    {
        $inQuote = false;
        $depth = 0;
        for ($index = 0; $index < strlen($value); $index++) {
            $character = $value[$index];
            if ($character === '"') {
                if ($inQuote && ($value[$index + 1] ?? null) === '"') {
                    $index++;
                    continue;
                }
                $inQuote = !$inQuote;
                continue;
            }
            if (!$inQuote && in_array($character, ['{', '['], true)) {
                $depth++;
            } elseif (!$inQuote && in_array($character, ['}', ']'], true)) {
                $depth--;
            } elseif (!$inQuote && $depth === 0 && $character === ';') {
                return [rtrim(substr($value, 0, $index)), trim(substr($value, $index + 1))];
            }
        }
        return [$value, null];
    }

    private function parseValue(string $raw): Value
    {
        $raw = trim($raw);
        if (preg_match('/^[a-z][a-z0-9_-]*\s*\(/i', $raw) === 1) {
            return new Value($raw, ValueKind::Opaque, null, false);
        }
        if ($raw !== '' && in_array($raw[0], ['{', '['], true)) {
            $structured = $this->parseJsonLike($raw);
            return new Value($raw, ValueKind::Json, json_decode(json_encode($structured, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION), true, 512, JSON_THROW_ON_ERROR), true, $structured);
        }
        if (str_contains($raw, "\n")) {
            return new Value($raw, ValueKind::Multiline, $this->parseScalar($raw), true);
        }
        return new Value($raw, ValueKind::Scalar, $this->parseScalar($raw), true);
    }

    private function parseScalar(string $value): mixed
    {
        $lower = strtolower($value);
        if ($lower === 'null') {
            return null;
        }
        if ($lower === 'true') {
            return true;
        }
        if ($lower === 'false') {
            return false;
        }
        if (preg_match('/^0b[01]+$/', $value) === 1) {
            return bindec(substr($value, 2));
        }
        if (preg_match('/^0x[0-9a-f]+$/i', $value) === 1) {
            return hexdec(substr($value, 2));
        }
        if (preg_match('/^0[0-7]+$/', $value) === 1) {
            return octdec($value);
        }
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }
        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
            return str_replace('""', '"', substr($value, 1, -1));
        }
        return $value;
    }

    private function parseJsonLike(string $value): mixed
    {
        $value = $this->stripJsonComments($value);
        $value = preg_replace('/,\s*([}\]])/', '$1', $value) ?? $value;
        $value = preg_replace_callback('/"((?:""|[^"])*)"/s', static fn (array $matches): string => json_encode(str_replace('""', '"', $matches[1]), JSON_THROW_ON_ERROR), $value) ?? $value;
        $decoded = json_decode($value);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->fail(sprintf('Error parsing JSON-like value: %s', json_last_error_msg()), 'jin.syntax.json');
            return null;
        }
        return $decoded;
    }

    private function stripJsonComments(string $value): string
    {
        return implode("\n", array_map(function (string $line): string {
            $inQuote = false;
            for ($index = 0; $index < strlen($line); $index++) {
                if ($line[$index] === '"') {
                    if ($inQuote && ($line[$index + 1] ?? null) === '"') {
                        $index++;
                        continue;
                    }
                    $inQuote = !$inQuote;
                } elseif (!$inQuote && $line[$index] === ';') {
                    return rtrim(substr($line, 0, $index));
                }
            }
            return $line;
        }, explode("\n", $value)));
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<string, mixed> $directives
     * @param array<string, mixed> $metadata
     */
    private function recordLegacyData(Assignment $assignment, string $key, array &$data, array &$directives, array &$metadata): void
    {
        $path = implode('.', $assignment->path()->segments());
        $metadata[$path] = [
            'leadingComments' => array_map(static fn (Comment $comment): string => $comment->text(), $assignment->comments()),
            'inlineComment' => $assignment->inlineComment()?->text(),
            'line' => $assignment->span()->startLine(),
            'source' => $assignment->span()->source()->canonicalPath(),
        ];
        $value = $assignment->value()->staticValue();
        if ($key === '--extends') {
            $directives['extends'] = preg_match('/^file\((.*)\)$/s', $assignment->value()->raw(), $matches) === 1 ? trim($matches[1], " \t\n\r\0\x0B\"") : $value;
        } elseif ($key === '--without') {
            $directives['without'] = is_array($value) ? array_values($value) : [is_scalar($value) ? (string) $value : ''];
        } else {
            Arr::set($data, $path, $value);
            if ($assignment->value()->kind() === ValueKind::Json) {
                $this->recordJsonMetadata($assignment->value()->raw(), $path, $metadata, $assignment->span()->startLine(), $assignment->span()->source()->canonicalPath());
                $this->fillJsonMetadata($value, $path, $metadata);
            }
        }
    }

    /** @param array<string, mixed> $metadata */
    private function fillJsonMetadata(mixed $value, string $path, array &$metadata): void
    {
        if (!is_array($value)) {
            return;
        }

        foreach ($value as $key => $child) {
            $childPath = $path . '.' . $key;
            $metadata[$childPath] ??= ['leadingComments' => [], 'leadingTrivia' => [], 'inlineComment' => null, 'synthetic' => true];
            $this->fillJsonMetadata($child, $childPath, $metadata);
        }
    }

    /** @param array<string, mixed> $metadata */
    private function recordJsonMetadata(string $raw, string $basePath, array &$metadata, int $startLine, string $source): void
    {
        $stack = [];
        $pending = [];
        foreach (explode("\n", $raw) as $offset => $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                $pending[] = ['type' => 'blank'];
                continue;
            }
            if (str_starts_with($trimmed, ';')) {
                $pending[] = ['type' => 'comment', 'text' => trim(substr($trimmed, 1))];
                continue;
            }
            if (preg_match('/^"((?:""|[^"])*)"\s*:\s*(.*)$/', $trimmed, $matches) === 1) {
                $key = str_replace('""', '"', $matches[1]);
                [, $inline] = $this->splitInlineComment($matches[2]);
                $metadata[implode('.', [...explode('.', $basePath), ...$stack, $key])] = [
                    'leadingComments' => array_values(array_map(
                        static fn (array $trivia): string => $trivia['text'],
                        array_filter($pending, static fn (array $trivia): bool => $trivia['type'] === 'comment'),
                    )),
                    'leadingTrivia' => $pending,
                    'inlineComment' => $inline,
                    'line' => $startLine + $offset,
                    'source' => $source,
                ];
                $pending = [];
                if (str_starts_with(ltrim($matches[2]), '{') || str_starts_with(ltrim($matches[2]), '[')) {
                    $stack[] = $key;
                }
            } elseif (str_starts_with($trimmed, '}') || str_starts_with($trimmed, ']')) {
                array_pop($stack);
            }
        }
    }

    private function span(SourceId $source, int $startLine, int $startColumn, int $endLine, int $endColumn): SourceSpan
    {
        return new SourceSpan($source, $startLine, $startColumn, $endLine, $endColumn);
    }

    private function fail(string $message, string $rule = 'jin.syntax.structure'): void
    {
        $this->diagnostics[] = new Diagnostic($rule, Severity::Error, $message);
    }
}
