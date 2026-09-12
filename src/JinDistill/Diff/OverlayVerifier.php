<?php

namespace JinDistill\Diff;

use JinDistill\Composition\ComposedDocument;
use JinDistill\Evaluation\VerificationResult;
use JinDistill\Source\Path;
use JinDistill\Syntax\Assignment;

/**
 * Proves statically that the parent definition, the planned removals, and the
 * generated assignments describe the same effective configuration as the target.
 */
final class OverlayVerifier
{
    /** @param list<Path> $removals */
    public function verify(ComposedDocument $parent, ComposedDocument $target, DifferenceSet $differences, array $removals): VerificationResult
    {
        $state = $this->values($parent);

        foreach ($removals as $path) {
            $this->remove($state, $path);
        }

        foreach ($differences->all() as $difference) {
            if ($difference->kind() === DifferenceKind::Removed) {
                continue;
            }

            $assignment = $difference->target();
            $state[$assignment->path()->toJsonPointer()] = $this->comparable($assignment);
        }

        $overlaid = $this->leaves($state);
        $expected = $this->leaves($this->values($target));
        $differencePaths = [];

        foreach (array_unique([...array_keys($overlaid), ...array_keys($expected)]) as $pointer) {
            if (!array_key_exists($pointer, $overlaid) || !array_key_exists($pointer, $expected) || $overlaid[$pointer] !== $expected[$pointer]) {
                $differencePaths[] = $pointer;
            }
        }

        sort($differencePaths);

        return new VerificationResult($differencePaths);
    }

    /** @return array<string, mixed> */
    private function values(ComposedDocument $document): array
    {
        $values = [];
        foreach ($document->document()->statements() as $statement) {
            if ($statement instanceof Assignment && !str_starts_with($statement->path()->segments()[0], '--')) {
                $values[$statement->path()->toJsonPointer()] = $this->comparable($statement);
            }
        }

        return $values;
    }

    private function comparable(Assignment $assignment): mixed
    {
        return $assignment->value()->isStaticallyKnown()
            ? $assignment->value()->staticValue()
            : 'raw:' . $assignment->value()->raw();
    }

    /** @param array<string, mixed> $state */
    private function remove(array &$state, Path $path): void
    {
        $pointer = $path->toJsonPointer();

        if (array_key_exists($pointer, $state)) {
            unset($state[$pointer]);
            return;
        }

        foreach ($state as $owner => $value) {
            if (!is_array($value) || !str_starts_with($pointer, $owner . '/')) {
                continue;
            }

            $segments = array_slice($path->segments(), count(explode('/', ltrim($owner, '/'))));
            $this->removeNested($value, $segments);
            $state[$owner] = $value;
        }
    }

    /**
     * @param array<array-key, mixed> $value
     * @param list<string> $segments
     */
    private function removeNested(array &$value, array $segments): void
    {
        $key = array_shift($segments);

        if ($key === null || !array_key_exists($key, $value)) {
            return;
        }

        if ($segments === []) {
            unset($value[$key]);
            return;
        }

        if (is_array($value[$key])) {
            $this->removeNested($value[$key], $segments);
        }
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function leaves(array $state): array
    {
        $leaves = [];
        foreach ($state as $pointer => $value) {
            $this->expand($pointer, $value, $leaves);
        }

        return $leaves;
    }

    /** @param array<string, mixed> $leaves */
    private function expand(string $pointer, mixed $value, array &$leaves): void
    {
        if (!is_array($value) || $value === []) {
            $leaves[$pointer] = $value;
            return;
        }

        foreach ($value as $key => $nested) {
            $this->expand($pointer . '/' . str_replace(['~', '/'], ['~0', '~1'], (string) $key), $nested, $leaves);
        }
    }
}
