<?php

namespace JinDistill\Composition;

use JinDistill\Analysis\AnalysisResult;
use JinDistill\Syntax\Assignment;
use JinDistill\Syntax\Document;
use JinDistill\Syntax\Value;
use JinDistill\Syntax\ValueKind;
use JinDistill\Syntax\Section;
use JinDistill\Source\Path;

final class DefinitionComposer
{
    public function compose(AnalysisResult $analysis): ComposedDocument
    {
        $statements = [];
        $positions = [];
        $opaque = [];
        $source = null;
        foreach (array_reverse($analysis->sourceGraph()->documents()) as $document) {
            $source ??= $document->source();
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
                            $value = $defined->value()->staticValue();
                            $segments = explode('/', ltrim(substr($path, strlen($prefix)), '/'));
                            if (!is_array($value) || !$this->removeNested($value, $segments)) {
                                continue;
                            }
                            $statements[$position] = new Assignment(
                                $defined->path(),
                                new Value(json_encode($value, JSON_THROW_ON_ERROR), ValueKind::Json, $value, true),
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
            foreach ($statement->comments() as $comment) {
                $renderable[] = $comment;
            }
            $renderable[] = $statement;
        }
        return new ComposedDocument(new Document($renderable, $source), $analysis->provenance());
    }

    /** @param list<string> $ancestor @param list<string> $path */
    private function isAncestor(array $ancestor, array $path): bool
    {
        return count($ancestor) < count($path)
            && array_slice($path, 0, count($ancestor)) === $ancestor;
    }

    /** @param list<string> $segments */
    private function removeNested(array &$value, array $segments): bool
    {
        $key = array_shift($segments);
        if ($key === null || !array_key_exists($key, $value)) {
            return false;
        }
        if ($segments === []) {
            unset($value[$key]);
            return true;
        }
        if (!is_array($value[$key])) {
            return false;
        }
        return $this->removeNested($value[$key], $segments);
    }

    private function merge(Assignment $parent, Assignment $child): Assignment
    {
        $left = $parent->value()->staticValue();
        $right = $child->value()->staticValue();
        if (!$parent->value()->isStaticallyKnown()
            || !$child->value()->isStaticallyKnown()
            || !is_array($left)
            || !is_array($right)
            || array_is_list($left)
            || array_is_list($right)) {
            return $this->inheritLeadingComments($parent, $child);
        }

        $value = array_replace_recursive($left, $right);
        return new Assignment(
            $child->path(),
            new Value(json_encode($value, JSON_THROW_ON_ERROR), ValueKind::Json, $value, true),
            $child->comments() === [] ? $parent->comments() : $child->comments(),
            $child->inlineComment(),
            $child->span(),
        );
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
        );
    }
}
