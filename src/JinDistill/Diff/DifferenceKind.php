<?php

namespace JinDistill\Diff;

enum DifferenceKind: string
{
    case Added = 'added';
    case Changed = 'changed';
    case Removed = 'removed';
    case MetadataChanged = 'metadata-changed';
}
