<?php

namespace JinDistill\Diagnostics;

enum Severity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';
}
