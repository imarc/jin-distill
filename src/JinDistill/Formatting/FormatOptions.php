<?php

namespace JinDistill\Formatting;

use InvalidArgumentException;

final class FormatOptions
{
    public function __construct(
        private string $indentation = "\t",
        private string $lineEnding = "\n",
        private CommentPolicy $commentPolicy = CommentPolicy::WinnerOnly,
        private OrderingPolicy $ordering = OrderingPolicy::CanonicalSections,
        private string $extendsPathStyle = ExtendsPathStyle::HiraethFile,
        private string $numericStyle = 'decimal',
        private string $stringQuoting = 'minimal-safe',
        private string $sectionReferences = 'explicit',
        private string $diffGranularity = 'assignment',
        private bool $metadataSensitiveDiffs = true,
    ) {
        if ($indentation === '' || !in_array($lineEnding, ["\n", "\r\n"], true)) {
            throw new InvalidArgumentException('Formatting indentation and line ending must be supported values.');
        }
    }

    public function indentation(): string { return $this->indentation; }
    public function lineEnding(): string { return $this->lineEnding; }
    public function commentPolicy(): CommentPolicy { return $this->commentPolicy; }
    public function ordering(): OrderingPolicy { return $this->ordering; }
    public function extendsPathStyle(): string { return $this->extendsPathStyle; }
    public function numericStyle(): string { return $this->numericStyle; }
    public function stringQuoting(): string { return $this->stringQuoting; }
    public function sectionReferences(): string { return $this->sectionReferences; }
    public function diffGranularity(): string { return $this->diffGranularity; }
    public function metadataSensitiveDiffs(): bool { return $this->metadataSensitiveDiffs; }

    public function withIndentation(string $indentation): self { return new self($indentation, $this->lineEnding, $this->commentPolicy, $this->ordering, $this->extendsPathStyle, $this->numericStyle, $this->stringQuoting, $this->sectionReferences, $this->diffGranularity, $this->metadataSensitiveDiffs); }
    public function withCommentPolicy(CommentPolicy $policy): self { return new self($this->indentation, $this->lineEnding, $policy, $this->ordering, $this->extendsPathStyle, $this->numericStyle, $this->stringQuoting, $this->sectionReferences, $this->diffGranularity, $this->metadataSensitiveDiffs); }
    public function withOrdering(OrderingPolicy $ordering): self { return new self($this->indentation, $this->lineEnding, $this->commentPolicy, $ordering, $this->extendsPathStyle, $this->numericStyle, $this->stringQuoting, $this->sectionReferences, $this->diffGranularity, $this->metadataSensitiveDiffs); }
    public function withExtendsPathStyle(string $style): self { return new self($this->indentation, $this->lineEnding, $this->commentPolicy, $this->ordering, $style, $this->numericStyle, $this->stringQuoting, $this->sectionReferences, $this->diffGranularity, $this->metadataSensitiveDiffs); }
}
