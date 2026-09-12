<?php

namespace JinDistill\Diff;

final class DiffOptions
{
    public function __construct(private bool $metadataSensitive = true, private bool $verifies = false)
    {
    }

    public function metadataSensitive(): bool { return $this->metadataSensitive; }
    public function verifies(): bool { return $this->verifies; }

    public function withMetadataSensitivity(bool $metadataSensitive): self
    {
        return new self($metadataSensitive, $this->verifies);
    }

    public function withVerification(bool $verifies): self
    {
        return new self($this->metadataSensitive, $verifies);
    }
}
