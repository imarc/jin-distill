<?php

namespace JinDistill\Analysis;

enum AnalysisMode: string
{
    case SourceOnly = 'source-only';
    case Evaluated = 'evaluated';
}
