<?php

namespace JinDistill\Decoders;

use JinDistill\Source\Path;

/** Records number tokens without interpreting or evaluating Jin expressions. */
final class NumericLexemeCollector
{
    /** @var array<string, string> */
    private array $lexemes = [];
    /** @var array<string, array<string, mixed>> */
    private array $listTrivia = [];
    private int $offset = 0;
    private string $source = '';

    /** @return array<string, string> */
    public function collect(string $source, Path $path): array
    {
        $this->source = $source;
        $this->offset = 0;
        $this->lexemes = [];
        $this->listTrivia = [];
        $this->value($path);
        return $this->lexemes;
    }

    /** @return array<string, array<string, mixed>> */
    public function listTrivia(): array
    {
        return $this->listTrivia;
    }

    private function value(Path $path): void
    {
        $this->skipTrivia();
        $character = $this->source[$this->offset] ?? null;
        if ($character === '{') {
            $this->offset++;
            while (true) {
                $this->skipTrivia();
                if (($this->source[$this->offset] ?? null) === '}') {
                    $this->offset++;
                    return;
                }
                if (($this->source[$this->offset] ?? null) !== '"') {
                    return;
                }
                $key = $this->quoted();
                $this->skipTrivia();
                if (($this->source[$this->offset] ?? null) !== ':') {
                    return;
                }
                $this->offset++;
                $this->value($path->append($key));
                $this->skipTrivia();
                if (($this->source[$this->offset] ?? null) !== ',') {
                    continue;
                }
                $this->offset++;
            }
        }
        if ($character === '[') {
            $this->offset++;
            $index = 0;
            while (true) {
                $triviaStart = $this->offset;
                $this->skipTrivia();
                if (($this->source[$this->offset] ?? null) === ']') {
                    $this->offset++;
                    return;
                }
                if ($this->offset >= strlen($this->source)) {
                    return;
                }
                $itemPath = $path->append((string) $index++);
                $trivia = $this->leadingTrivia(substr($this->source, $triviaStart, $this->offset - $triviaStart));
                if ($trivia !== []) {
                    $comments = [];
                    foreach ($trivia as $item) {
                        if ($item['type'] === 'comment') {
                            $comments[] = $item['text'] ?? '';
                        }
                    }
                    $this->listTrivia[$itemPath->toJsonPointer()] = [
                        'leadingComments' => $comments,
                        'leadingTrivia' => $trivia,
                        'inlineComment' => null,
                    ];
                }
                $this->value($itemPath);
                $this->skipTrivia();
                if (($this->source[$this->offset] ?? null) !== ',') {
                    continue;
                }
                $this->offset++;
            }
        }
        if ($character === '"') {
            $this->quoted();
            return;
        }

        $start = $this->offset;
        while (isset($this->source[$this->offset])
            && !str_contains(" \t\r\n,]};", $this->source[$this->offset])) {
            $this->offset++;
        }
        $token = substr($this->source, $start, $this->offset - $start);
        if (preg_match('/^(?:0[xX][0-9a-fA-F]+|0[bB][01]+|0[0-7]+|-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?)$/', $token) === 1) {
            $this->lexemes[$path->toJsonPointer()] = $token;
        }
    }

    /** @return list<array{type: string, text?: string}> */
    private function leadingTrivia(string $raw): array
    {
        $lines = explode("\n", $raw);
        $trivia = [];
        foreach ($lines as $index => $line) {
            $trimmed = trim($line);
            if (str_starts_with($trimmed, ';')) {
                $trivia[] = ['type' => 'comment', 'text' => trim(substr($trimmed, 1))];
            } elseif ($trimmed === '' && $index > 0 && $index < count($lines) - 1) {
                $trivia[] = ['type' => 'blank'];
            }
        }
        return $trivia;
    }

    private function skipTrivia(): void
    {
        while (isset($this->source[$this->offset])) {
            if (ctype_space($this->source[$this->offset])) {
                $this->offset++;
            } elseif ($this->source[$this->offset] === ';') {
                while (isset($this->source[$this->offset]) && $this->source[$this->offset] !== "\n") {
                    $this->offset++;
                }
            } else {
                return;
            }
        }
    }

    private function quoted(): string
    {
        $this->offset++;
        $value = '';
        while (isset($this->source[$this->offset])) {
            $character = $this->source[$this->offset++];
            if ($character !== '"') {
                $value .= $character;
            } elseif (($this->source[$this->offset] ?? null) === '"') {
                $value .= '"';
                $this->offset++;
            } else {
                return $value;
            }
        }
        return $value;
    }
}
