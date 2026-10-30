<?php

namespace JinDistill\Formatting;

enum OrderingPolicy: string
{
    case SourceOrder = 'source-order';
    case CanonicalSections = 'canonical-sections';
}
