<?php

namespace JinDistill\Diff;

final class DifferenceSet
{
    /** @param list<Difference> $differences */
    public function __construct(private array $differences)
    {
    }

    /** @return list<Difference> */
    public function all(): array
    {
        return $this->differences;
    }
}
