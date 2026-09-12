<?php

namespace JinDistill\Formatting;

use JinDistill\Exceptions\InvalidPathException;

final class ExtendsReference
{
    private function __construct(private string $relativePath, private ?string $applicationPath)
    {
    }

    public static function forOutput(string $parentPath, string $outputPath, ?string $applicationRoot = null): self
    {
        $parent = self::segments($parentPath);
        $output = self::segments(dirname(self::normalize($outputPath)));
        $shared = $parent;

        while ($shared !== [] && $output !== [] && $shared[0] === $output[0]) {
            array_shift($shared);
            array_shift($output);
        }

        return new self(
            implode('/', [...array_fill(0, count($output), '..'), ...$shared]),
            $applicationRoot === null ? null : self::rootRelativePath($parentPath, $applicationRoot),
        );
    }

    public function relativePath(): string { return $this->relativePath; }
    public function applicationPath(): ?string { return $this->applicationPath; }

    public function toSource(?FormatOptions $options = null): string
    {
        $options ??= new FormatOptions();

        if ($options->extendsPathStyle() === ExtendsPathStyle::BareRelative) {
            return $this->relativePath;
        }

        return sprintf('file(%s)', $this->applicationPath ?? $this->relativePath);
    }

    private static function rootRelativePath(string $parentPath, string $applicationRoot): string
    {
        $parent = self::segments($parentPath);
        $root = self::segments($applicationRoot);

        if (array_slice($parent, 0, count($root)) !== $root || count($parent) <= count($root)) {
            throw InvalidPathException::outsideApplicationRoot($parentPath, $applicationRoot);
        }

        return implode('/', array_slice($parent, count($root)));
    }

    /** @return list<string> */
    private static function segments(string $path): array
    {
        $path = trim(self::normalize($path), '/');

        return $path === '' ? [] : explode('/', $path);
    }

    private static function normalize(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
