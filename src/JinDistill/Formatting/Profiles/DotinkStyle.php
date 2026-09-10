<?php

namespace JinDistill\Formatting\Profiles;

use JinDistill\Formatting\ExtendsPathStyle;
use JinDistill\Formatting\FormatOptions;

final class DotinkStyle
{
    public static function v1(): FormatOptions
    {
        return (new FormatOptions())->withExtendsPathStyle(ExtendsPathStyle::BareRelative);
    }
}
