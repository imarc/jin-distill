<?php

namespace JinDistill\Composition;

use JinDistill\Analysis\AnalysisResult;
use JinDistill\Source\Path;
use JinDistill\Syntax\Assignment;
use JinDistill\Syntax\Document;
use JinDistill\Syntax\Section;
use JinDistill\Syntax\Value;
use JinDistill\Syntax\ValueKind;

final class DefinitionComposer
{
    public function compose(AnalysisResult $analysis): ComposedDocument
    {
        $statements = [];
        $positions = [];
        $opaque = [];
        $metadata = [];
        $source = null;
        foreach (array_reverse($analysis->sourceGraph()->documents()) as $document) {
            $source ??= $document->source();
            $metadata = array_replace($metadata, $document->metadata);
            foreach ($document->statements() as $statement) {
                if (!$statement instanceof Assignment) {
                    continue;
                }
                if ($statement->path()->segments() === ['--without']) {
                    foreach ((array) $statement->value()->staticValue() as $removedPath) {
                        if (!is_string($removedPath) || $removedPath === '') {
                            continue;
                        }
                        $removedSegments = explode('.', $removedPath);
                        foreach ($opaque as $ancestor) {
                            if ($this->isAncestor($ancestor->path()->segments(), $removedSegments)) {
                                throw new \JinDistill\Exceptions\UnflattenableDefinitionException([
                                    new CompositionConflict($ancestor->path()->toJsonPointer(), '/' . implode('/', $removedSegments)),
                                ]);
                            }
                        }
                        $path = '/' . str_replace('.', '/', $removedPath);
                        if (isset($positions[$path])) {
                            $statements[$positions[$path]] = null;
                            unset($positions[$path]);
                            continue;
                        }
                        foreach ($positions as $definedPath => $position) {
                            $defined = $statements[$position] ?? null;
                            if (!$defined instanceof Assignment || !$defined->value()->isStaticallyKnown()) {
                                continue;
                            }
                            $prefix = $defined->path()->toJsonPointer();
                            if (!str_starts_with($path, $prefix . '/')) {
                                continue;
                            }
                            $value = $defined->value()->structuredValue();
                            if ($value instanceof \stdClass) {
                                $value = json_decode(json_encode($value, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
                            }
                            $segments = explode('/', ltrim(substr($path, strlen($prefix)), '/'));
                            if (!$value instanceof \stdClass || !$this->removeNested($value, $segments)) {
                                continue;
                            }
                            $statements[$position] = new Assignment(
                                $defined->path(),
                                $this->jsonValue($value),
                                $defined->comments(),
                                $defined->inlineComment(),
                                $defined->span(),
                            );
                        }
                    }
                    continue;
                }
                if (str_starts_with($statement->path()->segments()[0], '--')) {
                    continue;
                }
                foreach ($opaque as $ancestor) {
                    if ($this->isAncestor($ancestor->path()->segments(), $statement->path()->segments())) {
                        throw new \JinDistill\Exceptions\UnflattenableDefinitionException([
                            new CompositionConflict($ancestor->path()->toJsonPointer(), $statement->path()->toJsonPointer()),
                        ]);
                    }
                }
                $path = $statement->path()->toJsonPointer();
                if (isset($positions[$path])) {
                    $existing = $statements[$positions[$path]];
                    $statements[$positions[$path]] = $existing instanceof Assignment
                        ? $this->merge($existing, $statement)
                        : $statement;
                    continue;
                }
                $positions[$path] = count($statements);
                $statements[] = $statement;
                if (!$statement->value()->isStaticallyKnown()) {
                    $opaque[] = $statement;
                }
            }
        }
        $source ??= new \JinDistill\Source\SourceId('memory://composed.jin', 'composed.jin');
        $renderable = [];
        $section = [];
        foreach (array_values(array_filter($statements)) as $statement) {
            $segments = $statement->path()->segments();
            $nextSection = count($segments) > 1 ? array_slice($segments, 0, -1) : [];
            if ($nextSection !== $section && $nextSection !== []) {
                $renderable[] = new Section(
                    Path::fromSegments($nextSection),
                    implode('.', $nextSection),
                    $statement->span(),
                );
            }
            $section = $nextSection;
            foreach ($statement->leadingTrivia() as $trivia) {
                $renderable[] = $trivia;
            }
            $renderable[] = $statement;
        }
        return new ComposedDocument(new Document($renderable, $source, [], [], $metadata), $analysis->provenance());
    }

    /** @param list<string> $ancestor @param list<string> $path */
    /**
     * @param list<string> $ancestor
     * @param list<string> $path
     */
    private function isAncestor(array $ancestor, array $path): bool
    {
        return count($ancestor) < count($path)
            && array_slice($path, 0, count($ancestor)) === $ancestor;
    }

    /** @param list<string> $segments */
    /**
     * @param list<string> $segments
     */
    private function removeNested(\stdClass $value, array $segments): bool
    {
        $key = array_shift($segments);
        if ($key === null || !property_exists($value, $key)) {
            return false;
        }
        if ($segments === []) {
            unset($value->{$key});
            return true;
        }
        if (!$value->{$key} instanceof \stdClass) {
            return false;
        }
        return $this->removeNested($value->{$key}, $segments);
    }

    private function merge(Assignment $parent, Assignment $child): Assignment
    {
        $left = $parent->value()->structuredValue();
        $right = $child->value()->structuredValue();
        if (!$parent->value()->isStaticallyKnown()
            || !$child->value()->isStaticallyKnown()
            || !$left instanceof \stdClass
            || !$right instanceof \stdClass) {
            return $this->inheritLeadingComments($parent, $child);
        }

        $value = $this->mergeObjects($left, $right);
        return new Assignment(
            $child->path(),
            $this->jsonValue($value),
            $child->comments() === [] ? $parent->comments() : $child->comments(),
            $child->inlineComment(),
            $child->span(),
            $child->comments() === [] ? $parent->leadingTrivia() : $child->leadingTrivia(),
        );
    }

    private function mergeObjects(\stdClass $left, \stdClass $right): \stdClass
    {
        $merged = clone $left;
        foreach (get_object_vars($right) as $name => $value) {
            $previous = $merged->{$name} ?? null;
            $merged->{$name} = $this->mergeNested($previous, $value);
        }
        return $merged;
    }

    private function mergeNested(mixed $left, mixed $right): mixed
    {
        if ($left instanceof \stdClass && $right instanceof \stdClass) {
            return $this->mergeObjects($left, $right);
        }
        if (is_array($left) && is_array($right)) {
            $merged = $left;
            foreach ($right as $index => $value) {
                $merged[$index] = $this->mergeNested($merged[$index] ?? null, $value);
            }
            return $merged;
        }
        return $right;
    }

    private function jsonValue(\stdClass $value): Value
    {
        $raw = json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        return new Value($raw, ValueKind::Json, json_decode($raw, true, 512, JSON_THROW_ON_ERROR), true, $value);
    }

    private function inheritLeadingComments(Assignment $parent, Assignment $child): Assignment
    {
        if ($child->comments() !== [] || $parent->comments() === []) {
            return $child;
        }

        return new Assignment(
            $child->path(),
            $child->value(),
            $parent->comments(),
            $child->inlineComment(),
            $child->span(),
            $parent->leadingTrivia(),
        );
    }
}
