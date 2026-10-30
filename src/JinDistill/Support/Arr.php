<?php

namespace JinDistill\Support;

class Arr
{
    /** @param array<array-key, mixed> $data */
    public static function set(array &$data, string $path, mixed $value): void
    {
        $segments = explode('.', $path);
        $target = &$data;

        foreach ($segments as $segment) {
            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                $target[$segment] = [];
            }

            $target = &$target[$segment];
        }

        $target = $value;
    }

    /** @param array<array-key, mixed> $data */
    public static function has(array $data, string $path): bool
    {
        $segments = explode('.', $path);

        foreach ($segments as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return false;
            }

            $data = $data[$segment];
        }

        return true;
    }

    /** @param array<array-key, mixed> $data */
    public static function delete(array &$data, string $path): void
    {
        $segments = explode('.', $path);
        $last = array_pop($segments);
        $target = &$data;

        foreach ($segments as $segment) {
            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                return;
            }

            $target = &$target[$segment];
        }

        unset($target[$last]);
    }

    /**
     * @param array<array-key, mixed> $parent
     * @param array<array-key, mixed> $child
     * @return array<array-key, mixed>
     */
    public static function mergeDistinct(array $parent, array $child): array
    {
        foreach ($child as $key => $value) {
            if (
                array_key_exists($key, $parent) &&
                is_array($parent[$key]) &&
                is_array($value) &&
                !array_is_list($parent[$key]) &&
                !array_is_list($value)
            ) {
                $parent[$key] = self::mergeDistinct($parent[$key], $value);
                continue;
            }

            $parent[$key] = $value;
        }

        return $parent;
    }
}
