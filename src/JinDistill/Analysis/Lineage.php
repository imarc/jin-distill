<?php

namespace JinDistill\Analysis;

final class Lineage
{
    /**
     * @param list<Definition> $overridden
     * @param list<Definition> $removals
     */
    public function __construct(private Definition $winner, private array $overridden, private array $removals)
    {
    }

    public function winner(): Definition
    {
        return $this->winner;
    }

    /** @return list<Definition> */
    public function overridden(): array
    {
        return $this->overridden;
    }

    /** @return list<Definition> */
    public function removals(): array
    {
        return $this->removals;
    }
}
