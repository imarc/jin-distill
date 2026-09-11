<?php

namespace JinDistill\Formatting;

final class ExtendsReference
{
    private function __construct(private string $relativePath)
    {
    }

    public static function forOutput(string $parentPath, string $outputPath): self
    {
        $parent = explode('/', trim(str_replace('\\', '/', $parentPath), '/'));
        $output = explode('/', trim(dirname(str_replace('\\', '/', $outputPath)), '/'));
        while ($parent !== [] && $output !== [] && $parent[0] === $output[0]) {
            array_shift($parent);
            array_shift($output);
        }
        return new self(implode('/', [...array_fill(0, count($output), '..'), ...$parent]));
    }

    public function relativePath(): string { return $this->relativePath; }
}
