<?php

namespace JinDistill\Source;

use InvalidArgumentException;
use JinDistill\Syntax\Value;

final class RelativeExtendsResolver implements ExtendsResolver
{
    public function supports(Value $value): bool
    {
        return $value->isStaticallyKnown() && is_string($value->staticValue());
    }

    public function resolve(Value $value, SourceId $from): string
    {
        if (!$this->supports($value)) {
            throw new InvalidArgumentException('Relative extends values must be static strings.');
        }

        $static = $value->staticValue();
        $path = is_string($static) ? $static : '';

        return str_starts_with($path, '/') ? $path : dirname($from->canonicalPath()) . '/' . $path;
    }
}
