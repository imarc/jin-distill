<?php

namespace JinDistill\Decoders;

use JinDistill\Exceptions\InvalidStructureException;
use JinDistill\JinDocument;
use JinDistill\Support\Arr;

class JinDecoder implements DecoderInterface
{
    public function __construct(protected bool $strict = true)
    {
    }

    public function decode(string $contents, ?string $path = null): JinDocument
    {
        $contents = str_replace(["\r\n", "\r"], "\n", $contents);
        $lines = explode("\n", $contents);
        $data = [];
        $directives = [];
        $metadata = [];
        $section = null;
        $pendingComments = [];

        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];
            $trimmed = trim($line);

            if ($trimmed === '') {
                $pendingComments = [];
                continue;
            }

            if (str_starts_with(ltrim($line), ';')) {
                $pendingComments[] = trim(substr(ltrim($line), 1));
                continue;
            }

            if (preg_match('/^\s*\[([^\]]+)\]/', $line, $matches)) {
                $section = trim($matches[1]);
                $this->attachMetadata($metadata, $section, $pendingComments, null, $i + 1, $path);
                $pendingComments = [];
                if (!isset($data[$section])) {
                    Arr::set($data, $section, []);
                }
                continue;
            }

            if (!preg_match('/^\s*([A-Za-z0-9_.:\-]+|--[A-Za-z0-9_.:\-]+)\s*=\s*(.*)$/', $line, $matches)) {
                $this->fail(sprintf('Malformed Jin line %d: %s', $i + 1, $line));
                continue;
            }

            $key = $matches[1];
            $rawValue = $matches[2];
            $startLine = $i + 1;

            if ($this->startsBalancedValue($rawValue)) {
                while (!$this->isBalanced($rawValue) && $i + 1 < count($lines)) {
                    $i++;
                    $rawValue .= "\n" . $lines[$i];
                }
            }

            [$valueSource, $inlineComment] = $this->splitInlineComment($rawValue);
            $value = $this->parseValue($valueSource);

            if ($key === '--extends') {
                $directives['extends'] = $this->parseExtends($valueSource, $value);
                $this->attachMetadata($metadata, '--extends', $pendingComments, $inlineComment, $startLine, $path);
            } elseif ($key === '--without') {
                $directives['without'] = is_array($value) ? array_values($value) : [(string) $value];
                $this->attachMetadata($metadata, '--without', $pendingComments, $inlineComment, $startLine, $path);
            } else {
                $fullPath = $section ? $section . '.' . $key : $key;
                Arr::set($data, $fullPath, $value);
                $this->attachMetadata($metadata, $fullPath, $pendingComments, $inlineComment, $startLine, $path);
            }

            $pendingComments = [];
        }

        return new JinDocument($data, $directives, $metadata, $path);
    }

    public function decodeFile(string $path): JinDocument
    {
        if (!is_readable($path)) {
            throw new InvalidStructureException(sprintf('Cannot read Jin file: %s', $path));
        }

        return $this->decode(file_get_contents($path), $path);
    }

    protected function parseExtends(string $source, mixed $value): string
    {
        $source = trim($source);
        if (preg_match('/^file\((.*)\)$/', $source, $matches)) {
            return trim($matches[1], " \t\n\r\0\x0B\"");
        }

        return (string) $value;
    }

    protected function parseValue(string $value): mixed
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

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
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }
        if ($value[0] === '"' && substr($value, -1) === '"') {
            return str_replace('""', '"', substr($value, 1, -1));
        }
        if (in_array($value[0], ['{', '['], true)) {
            return $this->parseJsonLike($value);
        }

        return $value;
    }

    protected function parseJsonLike(string $value): mixed
    {
        $json = $this->stripComments($value);
        $json = preg_replace('/,\s*([}\]])/', '$1', $json);
        $json = str_replace(["\n", "\t"], [' ', ' '], $json);
        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->fail(sprintf('Error parsing JSON-like value: %s', json_last_error_msg()));
            return null;
        }

        return $decoded;
    }

    protected function startsBalancedValue(string $value): bool
    {
        $value = ltrim($value);
        return $value !== '' && in_array($value[0], ['{', '['], true);
    }

    protected function isBalanced(string $value): bool
    {
        $stack = [];
        $inQuote = false;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($char === '"') {
                if ($inQuote && ($value[$i + 1] ?? null) === '"') {
                    $i++;
                    continue;
                }
                $inQuote = !$inQuote;
                continue;
            }
            if ($inQuote) {
                continue;
            }
            if ($char === '{' || $char === '[') {
                $stack[] = $char;
            } elseif ($char === '}' || $char === ']') {
                array_pop($stack);
            }
        }

        return !$inQuote && $stack === [];
    }

    protected function splitInlineComment(string $value): array
    {
        $inQuote = false;
        $depth = 0;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($char === '"') {
                if ($inQuote && ($value[$i + 1] ?? null) === '"') {
                    $i++;
                    continue;
                }
                $inQuote = !$inQuote;
                continue;
            }
            if ($inQuote) {
                continue;
            }
            if ($char === '{' || $char === '[') {
                $depth++;
            } elseif ($char === '}' || $char === ']') {
                $depth--;
            } elseif ($char === ';' && $depth === 0) {
                return [rtrim(substr($value, 0, $i)), trim(substr($value, $i + 1))];
            }
        }

        return [$value, null];
    }

    protected function stripComments(string $value): string
    {
        $lines = explode("\n", $value);
        foreach ($lines as &$line) {
            [$line] = $this->splitInlineComment($line);
        }
        return implode("\n", $lines);
    }

    protected function attachMetadata(array &$metadata, string $path, array $leading, ?string $inline, int $line, ?string $source): void
    {
        if ($leading === [] && $inline === null && $source === null) {
            return;
        }

        $metadata[$path] = [
            'leadingComments' => $leading,
            'inlineComment' => $inline,
            'line' => $line,
            'source' => $source,
        ];
    }

    protected function fail(string $message): void
    {
        if ($this->strict) {
            throw new InvalidStructureException($message);
        }
    }
}
