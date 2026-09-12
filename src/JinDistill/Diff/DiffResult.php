<?php

namespace JinDistill\Diff;

use JinDistill\Analysis\ProvenanceIndex;
use JinDistill\Diagnostics\Diagnostic;
use JinDistill\Evaluation\VerificationResult;
use JinDistill\Source\Path;

final class DiffResult
{
    /**
     * @param list<Path> $removals
     * @param list<Diagnostic> $diagnostics
     */
    public function __construct(
        private string $content,
        private DifferenceSet $differences,
        private array $removals,
        private ProvenanceIndex $provenance,
        private array $diagnostics = [],
        private ?VerificationResult $verification = null,
    ) {
    }

    public function content(): string { return $this->content; }
    public function differences(): DifferenceSet { return $this->differences; }
    /** @return list<Path> */
    public function removals(): array { return $this->removals; }
    public function provenance(): ProvenanceIndex { return $this->provenance; }
    /** @return list<Diagnostic> */
    public function diagnostics(): array { return $this->diagnostics; }
    public function verification(): ?VerificationResult { return $this->verification; }
}
