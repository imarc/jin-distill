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
            if ($difference->kind() === DifferenceKind::Removed) {
                $paths[] = $difference->path();
            }
        }
        return $paths;
    }
}
