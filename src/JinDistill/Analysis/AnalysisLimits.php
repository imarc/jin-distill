<?php

namespace JinDistill\Analysis;

use JinDistill\Exceptions\AnalysisLimitException;

/**
 * Generous defaults that stop hostile or accidental inputs from exhausting
 * memory while never rejecting realistic configuration.
 */
final class AnalysisLimits
{
    public const DEFAULT_BYTES = 4194304;
    public const DEFAULT_SYNTAX_DEPTH = 32;
    public const DEFAULT_INHERITANCE_DEPTH = 16;
    public const DEFAULT_DIAGNOSTICS = 500;

    public function __construct(
        private int $bytes = self::DEFAULT_BYTES,
        private int $syntaxDepth = self::DEFAULT_SYNTAX_DEPTH,
        private int $inheritanceDepth = self::DEFAULT_INHERITANCE_DEPTH,
        private int $diagnostics = self::DEFAULT_DIAGNOSTICS,
    ) {
    }

    public function bytes(): int { return $this->bytes; }
    public function syntaxDepth(): int { return $this->syntaxDepth; }
    public function inheritanceDepth(): int { return $this->inheritanceDepth; }
    public function diagnostics(): int { return $this->diagnostics; }

    public function guardBytes(int $actual): void
    {
        if ($actual > $this->bytes) {
            throw AnalysisLimitException::exceeded('bytes', $this->bytes, $actual);
        }
    }

    public function guardSyntaxDepth(int $actual): void
    {
        if ($actual > $this->syntaxDepth) {
            throw AnalysisLimitException::exceeded('syntax depth', $this->syntaxDepth, $actual);
        }
    }

    public function guardInheritanceDepth(int $actual): void
    {
        if ($actual > $this->inheritanceDepth) {
            throw AnalysisLimitException::exceeded('inheritance depth', $this->inheritanceDepth, $actual);
        }
    }
}
