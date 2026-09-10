<?php

namespace JinDistill\Source;

use InvalidArgumentException;

final class Path
{
    private function __construct(private array $segments)
    {
    }

    public static function fromSegments(array $segments): self
    {
        foreach ($segments as $segment) {
            if (!is_string($segment) || $segment === '') {
                throw new InvalidArgumentException('Path segments must be non-empty strings.');
            }
        }

        return new self(array_values($segments));
    }

    public function segments(): array
    {
        return $this->segments;
    }

    public function append(string $segment): self
    {
        return self::fromSegments([...$this->segments, $segment]);
    }

    public function toJsonPointer(): string
    {
        return implode('', array_map(
            static fn(string $segment): string => '/' . str_replace(['~', '/'], ['~0', '~1'], $segment),
            $this->segments
        ));
    }
}
