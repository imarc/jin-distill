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
            $value = $difference->parent()?->value()->staticValue();

            if ($difference->kind() === DifferenceKind::Changed && is_array($value) && array_is_list($value)) {
                $paths[] = $difference->path();
            }
        }

        return $paths;
    }
}
