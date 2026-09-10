<?php

namespace JinDistill\Formatting;

use JinDistill\Syntax\Value;

final class ExtendsRenderer
{
    public function render(Value $value, FormatOptions $options): string
    {
        if (!$value->isStaticallyKnown() || !is_string($value->staticValue())) {
            return $value->raw();
        }

        $reference = $value->staticValue();

        if ($options->extendsPathStyle() === ExtendsPathStyle::BareRelative) {
            return $reference;
        }

        if (preg_match('/^file\s*\(/', $value->raw()) === 1) {
            return $value->raw();
        }

        return sprintf('file(%s)', $reference);
    }
}
