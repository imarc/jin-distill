<?php

namespace JinDistill\Formats;

use JinDistill\Exceptions\InvalidStructureException;
use JinDistill\JinDocument;

class JinFormat implements FormatInterface
{
    protected string $data = '';
    /** @var array<string, mixed> */
    protected array $metadata = [];
    protected bool $comments = false;

    public function __construct(
        protected int $boundary = 1,
        protected string $tabs = "\t",
        protected bool $strict = true,
    ) {
    }

    public function encode(array $data, bool $extensions = true): string
    {
        $directives = [];

        if (array_key_exists('--extends', $data)) {
            $directives['extends'] = $data['--extends'];
            unset($data['--extends']);
        }

        if (array_key_exists('--without', $data)) {
            $directives['without'] = (array) $data['--without'];
            unset($data['--without']);
        }

        return $this->encodeDocument(new JinDocument($data, $directives), $extensions);
    }

    public function encodeDocument(JinDocument $document, bool $extensions = true, bool $comments = false): string
    {
        $this->data = '';
        $this->metadata = $document->metadata;
        $this->comments = $comments;

        if ($extensions) {
            $this->writeDirectives($document->directives);
        }

        $this->writeTopLevel($document->data);

        return rtrim($this->data) . "\n";
    }

    /** @param array<string, mixed> $directives */
    protected function writeDirectives(array $directives): void
    {
        $wrote = false;

        if (!empty($directives['extends'])) {
            $this->writeComments('--extends', 0);
            $this->write(sprintf('--extends = file(%s)', is_scalar($directives['extends']) ? $directives['extends'] : ''));
            $this->writeInlineComment('--extends');
            $this->write("\n");
            $wrote = true;
        }

        if (!empty($directives['without'])) {
            $without = array_values((array) $directives['without']);

            $this->writeComments('--without', 0);
            $this->write("--without = [\n");
            foreach ($without as $path) {
                $this->writeTabs(1);
                $this->write(sprintf("%s,\n", $this->quoteJsonString(is_scalar($path) ? (string) $path : '')));
            }
            $this->write("]\n");

            $wrote = true;
        }

        if ($wrote) {
            $this->write("\n");
        }
    }

    /** @param array<array-key, mixed> $data */
    protected function writeTopLevel(array $data): void
    {
        $first = true;

        foreach ($data as $key => $value) {
            if (!$first) {
                $this->write("\n");
            }

            if (is_array($value) && !array_is_list($value)) {
                $this->writeComments((string) $key, 0);
                $this->write(sprintf('[%s]', $key));
                $this->writeInlineComment((string) $key);
                $this->write("\n\n");
                $this->writeIniFields($value, 1, (string) $key);
            } elseif (is_array($value)) {
                $this->writeComments((string) $key, 0);
                $this->write(sprintf('%s = %s', $key, $this->encodeJsonValue($value, 0, (string) $key)));
                $this->writeInlineComment((string) $key);
                $this->write("\n");
            } else {
                $this->writeComments((string) $key, 0);
                $this->writeIniAssignment((string) $key, $value, 0, (string) $key);
            }

            $first = false;
        }
    }

    /** @param array<array-key, mixed> $fields */
    protected function writeIniFields(array $fields, int $depth, string $path): void
    {
        $first = true;

        foreach ($fields as $key => $value) {
            if (!$first) {
                $this->write("\n");
            }

            $fieldPath = $path . '.' . $key;
            $this->writeComments($fieldPath, $depth);

            if (is_array($value) && $depth >= $this->boundary) {
                $this->writeTabs($depth);
                $this->write(sprintf('%s = %s', $key, $this->encodeJsonValue($value, $depth, $fieldPath)));
            } elseif (is_array($value)) {
                if (array_is_list($value)) {
                    if ($this->strict) {
                        throw new InvalidStructureException('List arrays cannot be represented before the JSON boundary.');
                    }

                    $this->writeTabs($depth);
                    $this->write(sprintf('%s = %s', $key, $this->encodeJsonValue($value, $depth, $fieldPath)));
                } else {
                    $this->writeTabs($depth);
                    $this->write(sprintf('%s = %s', $key, $this->encodeJsonValue($value, $depth, $fieldPath)));
                }
            } else {
                $this->writeIniAssignment((string) $key, $value, $depth, $fieldPath);
            }

            $first = false;
        }
    }

    protected function writeIniAssignment(string $key, mixed $value, int $depth, string $path): void
    {
        $this->writeTabs($depth);
        $this->write(sprintf('%s = %s', $key, $this->encodeIniValue($value)));
        $this->writeInlineComment($path);
        $this->write("\n");
    }

    protected function encodeJsonValue(mixed $value, int $depth, ?string $path = null): string
    {
        if (!is_array($value)) {
            return $this->encodeJsonScalar($value);
        }

        if (array_is_list($value)) {
            if ($value === []) {
                return '[]';
            }

            $output = "[\n";
            foreach ($value as $item) {
                $this->appendTabsTo($output, $depth + 1);
                $output .= $this->encodeJsonValue($item, $depth + 1, $path) . ",\n";
            }
            $this->appendTabsTo($output, $depth);
            return $output . ']';
        }

        if (count($value) === 0) {
            return '{}';
        }

        $output = "{\n";
        foreach ($value as $key => $item) {
            $childPath = $path ? $path . '.' . $key : (string) $key;
            $this->appendCommentsTo($output, $childPath, $depth + 1);
            $this->appendTabsTo($output, $depth + 1);
            $output .= sprintf('%s: %s,', $this->quoteJsonString((string) $key), $this->encodeJsonValue($item, $depth + 1, $childPath));
            $this->appendInlineCommentTo($output, $childPath);
            $output .= "\n";
        }
        $this->appendTabsTo($output, $depth);
        return $output . '}';
    }

    protected function encodeJsonScalar(mixed $value): string
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

        return $this->quoteJsonString(is_scalar($value) ? (string) $value : '');
    }

    protected function encodeIniValue(mixed $value): string
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

        $value = is_scalar($value) ? (string) $value : '';

        if ($this->shouldQuoteIniString($value)) {
            return $this->quoteJsonString($value);
        }

        return $value;
    }

    protected function shouldQuoteIniString(string $value): bool
    {
        return $value === ''
            || trim($value) !== $value
            || preg_match('/[;\n{}\[\]"]/', $value)
            || in_array(strtolower($value), ['true', 'false', 'null'], true)
            || is_numeric($value);
    }

    protected function quoteJsonString(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }

    protected function writeComments(string $path, int $depth): void
    {
        foreach ($this->leadingComments($path) as $comment) {
            $this->writeTabs($depth);
            $this->write('; ' . $comment . "\n");
        }
    }

    /** @return list<string> */
    protected function leadingComments(string $path): array
    {
        $metadata = $this->metadata[$path] ?? null;

        if (!$this->comments || !is_array($metadata) || !is_array($metadata['leadingComments'] ?? null)) {
            return [];
        }

        $comments = [];

        foreach ($metadata['leadingComments'] as $comment) {
            $comments[] = is_scalar($comment) ? (string) $comment : '';
        }

        return $comments;
    }

    protected function inlineComment(string $path): ?string
    {
        $metadata = $this->metadata[$path] ?? null;

        if (!$this->comments || !is_array($metadata) || !is_scalar($metadata['inlineComment'] ?? null)) {
            return null;
        }

        return (string) $metadata['inlineComment'];
    }

    protected function writeInlineComment(string $path): void
    {
        $comment = $this->inlineComment($path);

        if ($comment === null) {
            return;
        }

        $this->write(' ; ' . $comment);
    }

    protected function writeTabs(int $depth): void
    {
        $this->data .= str_repeat($this->tabs, $depth);
    }

    protected function appendTabsTo(string &$output, int $depth): void
    {
        $output .= str_repeat($this->tabs, $depth);
    }

    protected function appendCommentsTo(string &$output, string $path, int $depth): void
    {
        foreach ($this->leadingComments($path) as $comment) {
            $this->appendTabsTo($output, $depth);
            $output .= '; ' . $comment . "\n";
        }
    }

    protected function appendInlineCommentTo(string &$output, string $path): void
    {
        $comment = $this->inlineComment($path);

        if ($comment === null) {
            return;
        }

        $output .= ' ; ' . $comment;
    }

    protected function write(string $string): void
    {
        $this->data .= $string;
    }
}
