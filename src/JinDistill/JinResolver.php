<?php

namespace JinDistill;

use JinDistill\Decoders\JinDecoder;
use JinDistill\Exceptions\InvalidStructureException;
use JinDistill\Support\Arr;

class JinResolver
{
    public function __construct(protected JinDecoder $decoder)
    {
    }

    public function resolveFile(string $path, bool $directives = false): JinDocument
    {
        $document = $this->decoder->decodeFile($path);

        return $this->resolveDocument($document, $directives);
    }

    public function resolveDocument(JinDocument $document, bool $directives = false, array $seen = []): JinDocument
    {
        $identity = $this->identity($document->path);

        if ($identity !== null) {
            if (in_array($identity, $seen, true)) {
                throw new InvalidStructureException(sprintf('Circular extends detected for %s', $identity));
            }

            $seen[] = $identity;
        }

        if (empty($document->directives['extends'])) {
            return new JinDocument($document->data, $directives ? $document->directives : [], $document->metadata, $document->path);
        }

        $parentPath = $this->resolveParentPath($document);
        $parent = $this->decoder->decodeFile($parentPath);
        $resolvedParent = $this->resolveDocument($parent, false, $seen);
        $parentData = $resolvedParent->data;

        foreach (($document->directives['without'] ?? []) as $path) {
            Arr::delete($parentData, $path);
        }

        return new JinDocument(
            Arr::mergeDistinct($parentData, $document->data),
            $directives ? $document->directives : [],
            Arr::mergeDistinct($resolvedParent->metadata, $document->metadata),
            $document->path
        );
    }

    protected function resolveParentPath(JinDocument $document): string
    {
        $extends = $document->directives['extends'];
        $base = $document->path ? dirname($document->path) : getcwd();
        $candidate = $this->isAbsolutePath($extends) ? $extends : $base . DIRECTORY_SEPARATOR . $extends;
        $real = realpath($candidate);

        if ($real === false || !is_readable($real)) {
            throw new InvalidStructureException(sprintf('Cannot resolve extended Jin file: %s', $extends));
        }

        return $real;
    }

    protected function identity(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        return realpath($path) ?: $path;
    }

    protected function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}
