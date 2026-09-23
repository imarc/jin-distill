<?php

namespace JinDistill\Sync;

final class StaticChange
{
    /** @param array{source: string, line: int}|null $previousLocation
     * @param array{source: string, line: int}|null $currentLocation
     */
    public function __construct(
        private string $root,
        private string $kind,
        private string $path,
        private ?StaticNode $previous,
        private ?StaticNode $current,
        private ?array $previousLocation = null,
        private ?array $currentLocation = null,
    ) {
    }

    public function root(): string
    {
        return $this->root;
    }
    public function kind(): string
    {
        return $this->kind;
    }
    public function path(): string
    {
        return $this->path;
    }
    /** @return array<string, mixed>|null */
    public function previous(): ?array
    {
        return $this->previous?->valueArray();
    }
    /** @return array<string, mixed>|null */
    public function current(): ?array
    {
        return $this->current?->valueArray();
    }
    /** @return array{source: string, line: int}|null */
    public function previousLocation(): ?array
    {
        return $this->previousLocation;
    }
    /** @return array{source: string, line: int}|null */
    public function currentLocation(): ?array
    {
        return $this->currentLocation;
    }
}
