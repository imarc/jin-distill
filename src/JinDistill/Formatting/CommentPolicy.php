<?php

namespace JinDistill\Formatting;

enum CommentPolicy: string
{
    case WinnerOnly = 'winner-only';
    case NearestDefinition = 'nearest-definition';
}
