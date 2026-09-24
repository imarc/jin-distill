<?php

namespace JinDistill\Formatting\Profiles;

use JinDistill\Formatting\ExtendsPathStyle;
use JinDistill\Formatting\FormatOptions;
use JinDistill\Formatting\OrderingPolicy;

final class DotinkStyle
{
    public static function v1(): FormatOptions
    {
        return FormatOptions::legacyV1()->withOrdering(OrderingPolicy::SourceOrder)->withExtendsPathStyle(ExtendsPathStyle::BareRelative);
    }

    public static function v2(): FormatOptions
    {
        return (new FormatOptions())->withExtendsPathStyle(ExtendsPathStyle::BareRelative);
    }
}
