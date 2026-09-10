<?php

namespace JinDistill\Composition;

final class CompositionConflict
{
    public function __construct(private string $parentPath, private string $childPath)
    {
    }

    public function parentPath(): string { return $this->parentPath; }
    public function childPath(): string { return $this->childPath; }
}
