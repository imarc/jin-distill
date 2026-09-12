<?php

namespace JinDistill\Diff;

use JinDistill\Composition\ComposedDocument;

final class RemovalPlanner
{
    /** @return list<\JinDistill\Source\Path> */
    public function plan(ComposedDocument $parent, ComposedDocument $target, DifferenceSet $differences): array
    {
        $paths = [];
        foreach ($differences->all() as $difference) {
            if ($difference->kind() === DifferenceKind::Removed
                || ($difference->kind() === DifferenceKind::Changed
                    && is_array($difference->parent()?->value()->staticValue())
                    && array_is_list($difference->parent()?->value()->staticValue()))) {
                $paths[] = $difference->path();
            }
        }
        $groups = [];
        foreach ($paths as $path) {
            $segments = $path->segments();
            if (count($segments) > 1) {
                $groups[implode('/', array_slice($segments, 0, -1))][] = $path;
            }
        }
        foreach ($groups as $prefix => $children) {
            $allRemoved = true;
            foreach ($parent->document()->statements() as $statement) {
                if (!$statement instanceof \JinDistill\Syntax\Assignment) {
                    continue;
                }
                if (array_slice($statement->path()->segments(), 0, count(explode('/', $prefix))) === explode('/', $prefix)
                    && !in_array($statement->path()->toJsonPointer(), array_map(static fn ($path): string => $path->toJsonPointer(), $children), true)) {
                    $allRemoved = false;
                }
            }
            if ($allRemoved) {
                $owner = \JinDistill\Source\Path::fromSegments(explode('/', $prefix));
                $collapsed = [];
                foreach ($paths as $path) {
                    if (!in_array($path, $children, true)) {
                        $collapsed[] = $path;
                        continue;
                    }
                    if ($path === $children[0]) {
                        $collapsed[] = $owner;
                    }
                }
                $paths = $collapsed;
            }
        }
        return $paths;
    }
}
