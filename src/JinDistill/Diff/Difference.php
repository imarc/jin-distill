<?php

namespace JinDistill\Diff;

use JinDistill\Source\Path;
use JinDistill\Syntax\Assignment;

final class Difference
{
    public function __construct(private DifferenceKind $kind, private Path $path, private ?Assignment $parent, private ?Assignment $target)
    {
    }

    public function kind(): DifferenceKind { return $this->kind; }
    public function path(): Path { return $this->path; }
    public function parent(): ?Assignment { return $this->parent; }
    public function target(): ?Assignment { return $this->target; }
}
