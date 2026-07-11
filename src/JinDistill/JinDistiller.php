<?php

namespace JinDistill;

use JinDistill\Decoders\JinDecoder;
use JinDistill\Formats\JinFormat;

class JinDistiller
{
    protected JinDecoder $decoder;
    protected JinResolver $resolver;
    protected JinFormat $format;

    public function __construct(
        ?JinDecoder $decoder = null,
        ?JinResolver $resolver = null,
        ?JinFormat $format = null,
    ) {
        $this->decoder = $decoder ?? new JinDecoder();
        $this->resolver = $resolver ?? new JinResolver($this->decoder);
        $this->format = $format ?? new JinFormat();
    }

    public function decode(string $contents, ?string $path = null): JinDocument
    {
        return $this->decoder->decode($contents, $path);
    }

    public function decodeFile(string $path): JinDocument
    {
        return $this->decoder->decodeFile($path);
    }

    public function resolveFile(string $path): JinDocument
    {
        return $this->resolver->resolveFile($path);
    }

    public function normalize(string $contents, ?string $path = null, bool $extensions = true): string
    {
        return $this->format->encodeDocument($this->decode($contents, $path), $extensions);
    }

    public function normalizeFile(string $path, bool $extensions = true): string
    {
        return $this->format->encodeDocument($this->decodeFile($path), $extensions);
    }

    public function flattenFile(string $path): string
    {
        return $this->format->encodeDocument($this->resolveFile($path), false);
    }
}
