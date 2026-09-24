<?php

namespace JinDistill\Formatting\Profiles;

use JinDistill\Formatting\FormatOptions;
use JinDistill\Formatting\OrderingPolicy;

final class ImarcStyle
{
    public static function v1(): FormatOptions
    {
        return FormatOptions::legacyV1()->withOrdering(OrderingPolicy::SourceOrder);
    }

    public static function v2(): FormatOptions
    {
        return new FormatOptions();
    }
}
