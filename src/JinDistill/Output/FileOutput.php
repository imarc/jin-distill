<?php

namespace JinDistill\Output;

use RuntimeException;

class FileOutput implements OutputInterface
{
    /** @var resource */
    protected $file;

    public function __construct(string $file)
    {
        $handle = fopen($file, 'w');

        if ($handle === false) {
            throw new RuntimeException(sprintf('Cannot open output file: %s', $file));
        }

        $this->file = $handle;
    }

    public function __destruct()
    {
        fclose($this->file);
    }

    public function write(string $data): void
    {
        fwrite($this->file, $data);
    }
}
