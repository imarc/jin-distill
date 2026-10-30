<?php

namespace JinDistill\Formatting;

enum LeadingCommentPolicy: string
{
    case WinnerOnly = 'winner-only';
    case NearestDefinition = 'nearest-definition';
}
