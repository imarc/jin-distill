<?php
namespace JinDistill\Diff;
final class DiffResult { public function __construct(private string $content, private DifferenceSet $differences, private array $removals) {} public function content(): string { return $this->content; } public function differences(): DifferenceSet { return $this->differences; } public function removals(): array { return $this->removals; } }
