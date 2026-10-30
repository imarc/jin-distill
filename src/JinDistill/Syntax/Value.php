<?php

namespace JinDistill\Syntax;

final class Value
{
    public function __construct(private string $raw, private ValueKind $kind, private mixed $staticValue, private bool $staticallyKnown, private mixed $structuredValue = null)
    {
    }

    public function raw(): string
    {
        return $this->raw;
    }

    public function kind(): ValueKind
    {
        return $this->kind;
    }

    public function staticValue(): mixed
    {
        return $this->staticValue;
    }

    public function structuredValue(): mixed
    {
        return $this->kind === ValueKind::Json ? $this->structuredValue : $this->staticValue;
    }

    public function isStaticallyKnown(): bool
    {
        return $this->staticallyKnown;
    }
}
