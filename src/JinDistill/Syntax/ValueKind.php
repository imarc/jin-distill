<?php

namespace JinDistill\Syntax;

enum ValueKind: string
{
    case Scalar = 'scalar';
    case Json = 'json';
    case Multiline = 'multiline';
    case Opaque = 'opaque';
}
