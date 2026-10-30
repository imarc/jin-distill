<?php

namespace JinDistill\Source;

use JinDistill\Syntax\Value;

interface ExtendsResolver
{
    public function supports(Value $value): bool;

    public function resolve(Value $value, SourceId $from): string;
}
