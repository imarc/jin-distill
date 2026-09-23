<?php

namespace JinDistill\Sync;

use InvalidArgumentException;

/** @internal */
final class StaticNode
{
    /**
     * @param array<array-key, self> $entries
     * @param list<self> $items
     */
    private function __construct(
        private string $type,
        private bool|int|float|string|null $value = null,
        private array $entries = [],
        private array $items = [],
    ) {
    }

    public static function map(): self
    {
        return new self('map');
    }

    public static function capture(mixed $value, bool $opaque = false): self
    {
        if ($opaque) {
            if (!is_string($value)) {
                throw new InvalidArgumentException('Opaque value must be text.');
            }
            return new self('opaque', $value);
        }
        if ($value instanceof \stdClass) {
            $entries = [];
            foreach (get_object_vars($value) as $key => $child) {
                $entries[$key] = self::capture($child);
            }
            ksort($entries, SORT_STRING);
            return new self('map', entries: $entries);
        }
        if (is_array($value)) {
            return new self('list', items: array_values(array_map(self::capture(...), $value)));
        }
        if (is_float($value) && !is_finite($value)) {
            throw new InvalidArgumentException('Non-finite static value.');
        }
        return match (true) {
            $value === null => new self('null'),
            is_bool($value) => new self('boolean', $value),
            is_int($value) => new self('integer', $value),
            is_float($value) => new self('float', $value),
            is_string($value) => new self('string', $value),
            default => throw new InvalidArgumentException('Unsupported static value.'),
        };
    }

    public static function parse(mixed $input, int $depth = 0): self
    {
        if ($depth > 64 || !$input instanceof \stdClass || !is_string($input->type ?? null)) {
            throw new InvalidArgumentException('Invalid static lock node.');
        }
        $fields = array_keys(get_object_vars($input));
        sort($fields);
        $type = $input->type;
        if (!is_string($type)) {
            throw new InvalidArgumentException('Invalid static lock node type.');
        }
        if ($type === 'map') {
            if ($fields !== ['entries', 'type'] || !$input->entries instanceof \stdClass) {
                throw new InvalidArgumentException('Invalid static lock map.');
            }
            $entries = [];
            foreach (get_object_vars($input->entries) as $key => $child) {
                $entries[$key] = self::parse($child, $depth + 1);
            }
            ksort($entries, SORT_STRING);
            return new self('map', entries: $entries);
        }
        if ($type === 'list') {
            if ($fields !== ['items', 'type'] || !is_array($input->items)) {
                throw new InvalidArgumentException('Invalid static lock list.');
            }
            return new self('list', items: array_values(array_map(static fn (mixed $item): self => self::parse($item, $depth + 1), $input->items)));
        }
        if ($type === 'null' && $fields === ['type']) {
            return new self('null');
        }
        if ($fields !== ['type', 'value']) {
            throw new InvalidArgumentException('Invalid static lock scalar.');
        }
        $value = $input->value;
        $valid = match ($type) {
            'boolean' => is_bool($value),
            'integer' => is_int($value),
            'float' => is_float($value) && is_finite($value),
            'string', 'opaque' => is_string($value),
            default => false,
        };
        if (!$valid) {
            throw new InvalidArgumentException('Invalid static lock scalar.');
        }
        if ($type === 'boolean' && is_bool($value)) {
            return new self($type, $value);
        }
        if ($type === 'integer' && is_int($value)) {
            return new self($type, $value);
        }
        if ($type === 'float' && is_float($value)) {
            return new self($type, $value);
        }
        if (($type === 'string' || $type === 'opaque') && is_string($value)) {
            return new self($type, $value);
        }
        throw new InvalidArgumentException('Invalid static lock scalar.');
    }

    public function type(): string
    {
        return $this->type;
    }

    /** @return array<array-key, self> */
    public function entries(): array
    {
        return $this->entries;
    }

    /** @return list<self> */
    public function items(): array
    {
        return $this->items;
    }

    public function equals(self $other): bool
    {
        if ($this->type !== $other->type || $this->value !== $other->value
            || count($this->entries) !== count($other->entries)
            || count($this->items) !== count($other->items)) {
            return false;
        }
        foreach ($this->entries as $key => $node) {
            if (!isset($other->entries[$key]) || !$node->equals($other->entries[$key])) {
                return false;
            }
        }
        foreach ($this->items as $index => $node) {
            if (!$node->equals($other->items[$index])) {
                return false;
            }
        }
        return true;
    }

    /** @param list<string> $segments */
    public function withPath(array $segments, self $node): self
    {
        $key = array_shift($segments);
        if ($key === null || $this->type !== 'map') {
            throw new InvalidArgumentException('Static tree path needs a map.');
        }
        $entries = $this->entries;
        $entries[$key] = $segments === []
            ? $node
            : ($entries[$key] ?? self::map())->asMap()->withPath($segments, $node);
        ksort($entries, SORT_STRING);
        return new self('map', entries: $entries);
    }

    private function asMap(): self
    {
        return $this->type === 'map' ? $this : self::map();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        if ($this->type === 'map') {
            $entries = [];
            foreach ($this->entries as $key => $node) {
                $entries[$key] = $node->toArray();
            }
            return ['type' => 'map', 'entries' => (object) $entries];
        }
        if ($this->type === 'list') {
            return ['type' => 'list', 'items' => array_map(static fn (self $node): array => $node->toArray(), $this->items)];
        }
        return $this->type === 'null' ? ['type' => 'null'] : ['type' => $this->type, 'value' => $this->value];
    }

    /** @return array<string, mixed> */
    public function valueArray(): array
    {
        if ($this->type === 'map') {
            $entries = [];
            foreach ($this->entries as $key => $node) {
                $entries[$key] = $node->valueArray();
            }
            return ['type' => 'map', 'entries' => $entries];
        }
        if ($this->type === 'list') {
            return ['type' => 'list', 'items' => array_map(static fn (self $node): array => $node->valueArray(), $this->items)];
        }
        return $this->toArray();
    }

    public static function pointer(string $path, string $key): string
    {
        return $path . '/' . str_replace(['~', '/'], ['~0', '~1'], $key);
    }
}
