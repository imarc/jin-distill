<?php

namespace JinDistill\Diff;

use JinDistill\Composition\ComposedDocument;
use JinDistill\Syntax\Assignment;

final class DefinitionDiffer
{
    public function compare(ComposedDocument $parent, ComposedDocument $target): DifferenceSet
    {
        $parents = $this->assignments($parent);
        $targets = $this->assignments($target);
        $differences = [];
        foreach ($parents as $path => $assignment) {
            if (!isset($targets[$path])) {
                $differences[] = new Difference(DifferenceKind::Removed, $assignment->path(), $assignment, null);
                continue;
            }
            if ($assignment->value()->staticValue() !== $targets[$path]->value()->staticValue()) {
                $differences[] = new Difference(DifferenceKind::Changed, $assignment->path(), $assignment, $targets[$path]);
            }
        }
        foreach ($targets as $path => $assignment) {
            if (!isset($parents[$path])) {
                $differences[] = new Difference(DifferenceKind::Added, $assignment->path(), null, $assignment);
            }
        }
        return new DifferenceSet($differences);
    }

    /** @return array<string, Assignment> */
    private function assignments(ComposedDocument $document): array
    {
        $assignments = [];
        foreach ($document->document()->statements() as $statement) {
            if ($statement instanceof Assignment) {
                $assignments[$statement->path()->toJsonPointer()] = $statement;
            }
        }
        return $assignments;
    }
}
