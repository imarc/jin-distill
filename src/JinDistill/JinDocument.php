<?php

namespace JinDistill;

class JinDocument
{
    public function __construct(
        public array $data = [],
        public array $directives = [],
        public array $metadata = [],
        public ?string $path = null,
    ) {
    }

    public function withData(array $data): self
    {
        return new self($data, $this->directives, $this->metadata, $this->path);
    }

    public function withoutDirectives(): self
    {
        return new self($this->data, [], $this->metadata, $this->path);
    }
}
