<?php

namespace JinDistill\Support;

class Arr
{
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
