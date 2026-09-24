<?php

namespace JinDistill\Formatting;

use JinDistill\Syntax\Value;

final class ExtendsRenderer
{
    public function render(Value $value, FormatOptions $options): string
    {
        if ($options->extendsPathStyle() === ExtendsPathStyle::BareRelative) {
            if ($value->isStaticallyKnown() && is_string($value->staticValue())) {
                return $value->staticValue();
            }

            if (preg_match('/^file\s*\(\s*([A-Za-z0-9_.\/\\-]+)\s*\)$/', $value->raw(), $matches) === 1) {
                return $matches[1];
            }

            return $value->raw();
        }

        if (!$value->isStaticallyKnown() || !is_string($value->staticValue())) {
            return $value->raw();
        }

        $reference = $value->staticValue();

        if (preg_match('/^file\s*\(/', $value->raw()) === 1) {
            return $value->raw();
        }

        return sprintf('file(%s)', $reference);
    }
}
