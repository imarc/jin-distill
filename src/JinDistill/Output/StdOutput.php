<?php

namespace JinDistill\Output;

/**
 * @deprecated 1.0.0 Echo returned workflow content explicitly.
 */
class StdOutput implements OutputInterface
{
    public function write(string $data): void
    {
        echo $data;
    }
}
