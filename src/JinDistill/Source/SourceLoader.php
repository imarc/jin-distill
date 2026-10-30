<?php

namespace JinDistill\Source;

interface SourceLoader
{
    public function load(string $reference, ?SourceId $from = null): LoadedSource;
}
