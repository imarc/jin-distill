<?php

namespace JinDistill\Decoders;

interface DecoderInterface
{
    public function decode(string $contents, ?string $path = null): mixed;

    public function decodeFile(string $path): mixed;
}
