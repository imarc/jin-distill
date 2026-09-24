<?php

namespace JinDistill\Formatting;

enum StringQuoting: string
{
    case MinimalSafe = 'minimal-safe';
    case Always = 'always';
}
