<?php

namespace JinDistill\Diagnostics;

use JinDistill\Source\Path;
use JinDistill\Source\SourceSpan;

final class Diagnostic
{
    public function __construct(
        private string $ruleId,
        private Severity $severity,
        private string $message,
        private ?Path $path = null,
        private ?SourceSpan $span = null,
        private ?string $suggestion = null,
    ) {
    }

    public function ruleId(): string
    {
        return $this->ruleId;
    }
    public function severity(): Severity
    {
        return $this->severity;
    }
    public function message(): string
    {
        return $this->message;
    }
    public function path(): ?Path
    {
        return $this->path;
    }
    public function span(): ?SourceSpan
    {
        return $this->span;
    }
    public function suggestion(): ?string
    {
        return $this->suggestion;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'rule' => $this->ruleId,
            'severity' => $this->severity->value,
            'message' => $this->message,
            'path' => $this->path?->toJsonPointer(),
            'span' => $this->span?->toArray(),
            'suggestion' => $this->suggestion,
        ];
    }
}
