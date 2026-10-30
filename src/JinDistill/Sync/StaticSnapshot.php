<?php

namespace JinDistill\Sync;

use InvalidArgumentException;

final class StaticSnapshot
{
    public const SCHEMA = 'jin-distill-static-sync-lock/1';
    private const MAX_JSON_BYTES = 33554432;

    /**
     * @param array<string, StaticNode> $roots
     * @param array<string, array<string, array{source: string, line: int}>> $locations
     */
    public function __construct(private array $roots, private array $locations = [])
    {
        if ($roots === []) {
            throw new InvalidArgumentException('Static snapshot needs at least one root.');
        }
        ksort($this->roots, SORT_STRING);
    }

    public function toJson(): string
    {
        $roots = [];
        foreach ($this->roots as $name => $node) {
            $root = $node->toArray();
            if (($this->locations[$name] ?? []) !== []) {
                $locations = $this->locations[$name];
                ksort($locations, SORT_STRING);
                $root['locations'] = (object) $locations;
            }
            $roots[$name] = $root;
        }
        $json = json_encode(['schema' => self::SCHEMA, 'roots' => (object) $roots], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        if (strlen($json) > self::MAX_JSON_BYTES) {
            throw new InvalidArgumentException('Static lock exceeds 32 MiB.');
        }
        return $json . "\n";
    }

    public static function fromJson(string $json): self
    {
        if (strlen($json) > self::MAX_JSON_BYTES) {
            throw new InvalidArgumentException('Static lock exceeds 32 MiB.');
        }
        try {
            $decoded = json_decode($json, false, 128, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new InvalidArgumentException('Invalid static lock JSON.', 0, $error);
        }
        if (!$decoded instanceof \stdClass || array_diff(array_keys(get_object_vars($decoded)), ['schema', 'roots']) !== []
            || count(get_object_vars($decoded)) !== 2
            || $decoded->schema !== self::SCHEMA || !$decoded->roots instanceof \stdClass) {
            throw new InvalidArgumentException('Unsupported or invalid static lock schema.');
        }
        $roots = [];
        $locations = [];
        foreach (get_object_vars($decoded->roots) as $name => $root) {
            if ($name === '' || !$root instanceof \stdClass) {
                throw new InvalidArgumentException('Invalid static lock root.');
            }
            if (property_exists($root, 'locations')) {
                $locations[$name] = self::parseLocations($root->locations);
                unset($root->locations);
            }
            $roots[$name] = StaticNode::parse($root);
        }
        return new self($roots, $locations);
    }

    /** @return array<string, array{source: string, line: int}> */
    private static function parseLocations(mixed $input): array
    {
        if (!$input instanceof \stdClass) {
            throw new InvalidArgumentException('Invalid static lock locations.');
        }
        $locations = [];
        foreach (get_object_vars($input) as $pointer => $location) {
            $fields = $location instanceof \stdClass ? array_keys(get_object_vars($location)) : [];
            sort($fields);
            if (!is_string($pointer) || ($pointer !== '' && (preg_match('/^(?:\/(?:[^~]|~[01])*)*$/', $pointer) !== 1))
                || !$location instanceof \stdClass || $fields !== ['line', 'source'] || !is_string($location->source) || $location->source === ''
                || !is_int($location->line) || $location->line < 1) {
                throw new InvalidArgumentException('Invalid static lock location.');
            }
            $locations[$pointer] = ['source' => $location->source, 'line' => $location->line];
        }
        return $locations;
    }

    public function compare(self $current): StaticComparison
    {
        $changes = [];
        $statuses = [];
        $names = array_unique([...array_keys($this->roots), ...array_keys($current->roots)]);
        sort($names, SORT_STRING);
        foreach ($names as $name) {
            $start = count($changes);
            $old = $this->roots[$name] ?? null;
            $new = $current->roots[$name] ?? null;
            if ($old === null || $new === null) {
                $changes[] = new StaticChange($name, $old === null ? 'added' : 'removed', '', $old, $new);
            } elseif (!$old->equals($new)) {
                $this->compareNodes($name, '', $old, $new, $current, $changes);
            }
            $statuses[$name] = StaticComparison::classify(array_slice($changes, $start));
        }
        return new StaticComparison($changes, $statuses);
    }

    /** @param list<StaticChange> $changes */
    private function compareNodes(string $root, string $path, StaticNode $old, StaticNode $new, self $current, array &$changes): void
    {
        if ($old->equals($new)) {
            return;
        }
        if ($old->type() === 'map' && $new->type() === 'map') {
            $beforeEntries = $old->entries();
            $afterEntries = $new->entries();
            $keys = array_unique([...array_keys($beforeEntries), ...array_keys($afterEntries)]);
            sort($keys, SORT_STRING);
            foreach ($keys as $key) {
                $pointer = StaticNode::pointer($path, (string) $key);
                $before = $beforeEntries[$key] ?? null;
                $after = $afterEntries[$key] ?? null;
                if ($before === null || $after === null) {
                    $changes[] = $this->change($root, $pointer, $before === null ? 'added' : 'removed', $before, $after, $current);
                } else {
                    $this->compareNodes($root, $pointer, $before, $after, $current, $changes);
                }
            }
            return;
        }
        if ($old->type() === 'list' && $new->type() === 'list') {
            $before = $old->items();
            $after = $new->items();
            $prefix = true;
            for ($i = 0; $i < min(count($before), count($after)); $i++) {
                if (!$before[$i]->equals($after[$i])) {
                    $prefix = false;
                    break;
                }
            }
            if ($prefix && count($before) !== count($after)) {
                for ($i = min(count($before), count($after)); $i < max(count($before), count($after)); $i++) {
                    $removed = count($before) > count($after);
                    $changes[] = $this->change($root, StaticNode::pointer($path, (string) $i), $removed ? 'removed' : 'added', $removed ? $before[$i] : null, $removed ? null : $after[$i], $current);
                }
                return;
            }
        }
        $changes[] = $this->change($root, $path, 'updated', $old, $new, $current);
    }

    private function change(string $root, string $path, string $kind, ?StaticNode $old, ?StaticNode $new, self $current): StaticChange
    {
        return new StaticChange($root, $kind, $path, $old, $new, $this->locations[$root][$path] ?? null, $current->locations[$root][$path] ?? null);
    }
}
