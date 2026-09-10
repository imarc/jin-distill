<?php

namespace JinDistill\Source;

use InvalidArgumentException;
use JinDistill\Syntax\Value;

final class FunctionExtendsResolver implements ExtendsResolver
{
    public function __construct(private string $function, private string $root)
    {
        $this->root = rtrim($root, '/');
    }

    public function supports(Value $value): bool
    {
        return $this->argument($value) !== null;
    }

    public function resolve(Value $value, SourceId $from): string
    {
        $argument = $this->argument($value);
        if ($argument === null) {
            throw new InvalidArgumentException('Extends value is not a supported static function call.');
        }

        return str_starts_with($argument, '/') ? $argument : $this->root . '/' . $argument;
    }

    private function argument(Value $value): ?string
    {
        if ($value->isStaticallyKnown() || preg_match(
            '/^' . preg_quote($this->function, '/') . '\s*\(\s*(?:"((?:""|[^"])*)"|([^()\s,]+))\s*\)$/s',
            $value->raw(),
            $matches,
        ) !== 1) {
            return null;
        }

        return str_replace('""', '"', $matches[1] !== '' ? $matches[1] : $matches[2]);
    }
}
