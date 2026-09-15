<?php

namespace JinDistill\Output;

/**
 * @deprecated 1.0.0 Core JinDistill workflows return content and never write output.
 */
interface OutputInterface
{
    public function write(string $data): void;
}
