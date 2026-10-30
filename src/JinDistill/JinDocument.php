<?php

namespace JinDistill;

class JinDocument
{
    /**
     * @param array<array-key, mixed> $data
     * @param array<string, mixed> $directives
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public array $data = [],
        public array $directives = [],
        public array $metadata = [],
        public ?string $path = null,
    ) {
    }

    /** @param array<array-key, mixed> $data */
    public function withData(array $data): self
    {
        return new self($data, $this->directives, $this->metadata, $this->path);
    }

    public function withoutDirectives(): self
    {
        return new self($this->data, [], $this->metadata, $this->path);
    }
}
