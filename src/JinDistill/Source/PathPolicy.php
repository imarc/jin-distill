<?php

namespace JinDistill\Source;

use InvalidArgumentException;

final class PathPolicy
{
    /** @var list<string> */
    private array $roots;

    /** @param list<string> $roots */
    public function __construct(array $roots)
    {
        $this->roots = array_map(static fn (string $root): string => rtrim($root, '/'), $roots);
    }

    public function assertAllowed(string $path): void
    {
        foreach ($this->roots as $root) {
            if ($path === $root || str_starts_with($path, $root . '/')) {
                return;
            }
        }

        throw new InvalidArgumentException(sprintf('Jin source is outside allowed roots: %s', $path));
    }

    public function firstRoot(): string
    {
        if ($this->roots === []) {
            throw new InvalidArgumentException('At least one allowed source root is required.');
        }

        return $this->roots[0];
    }
}
