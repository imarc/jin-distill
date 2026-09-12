<?php

namespace JinDistill\Diff;

use JinDistill\Composition\ComposedDocument;
use JinDistill\Composition\CompositionConflict;
use JinDistill\Diagnostics\Diagnostic;
use JinDistill\Diagnostics\Severity;
use JinDistill\Exceptions\UndiffableDefinitionException;
use JinDistill\Formatting\FormatOptions;
use JinDistill\Formatting\JinRenderer;
use JinDistill\Source\Path;
use JinDistill\Syntax\Assignment;
use JinDistill\Syntax\Document;
use JinDistill\Syntax\Section;
use JinDistill\Syntax\Statement;
use JinDistill\Syntax\Value;
use JinDistill\Syntax\ValueKind;

final class Differ
{
    public function diff(
        ComposedDocument $parent,
        ComposedDocument $target,
        string $reference,
        ?DiffOptions $options = null,
        ?FormatOptions $format = null,
    ): DiffResult {
        $options ??= new DiffOptions();

        $this->guardOpaqueOverrides($parent, $target);

        $differences = (new DefinitionDiffer())->compare($parent, $target, $options);
        $removals = (new RemovalPlanner())->plan($parent, $target, $differences);
        $document = new Document($this->nodes($target, $reference, $differences, $removals), $target->document()->source());

        return new DiffResult(
            (new JinRenderer())->render($document, $format),
            $differences,
            $removals,
            $target->provenance(),
            $this->diagnostics($differences),
            $options->verifies() ? (new OverlayVerifier())->verify($parent, $target, $differences, $removals) : null,
        );
    }

    /**
     * @param list<Path> $removals
     * @return list<Statement>
     */
    private function nodes(ComposedDocument $target, string $reference, DifferenceSet $differences, array $removals): array
    {
        $span = $target->document()->statements()[0]->span();
        $nodes = [new Assignment(
            Path::fromSegments(['--extends']),
            new Value($reference, ValueKind::Scalar, $reference, true),
            [],
            null,
            $span,
        )];

        if ($removals !== []) {
            $nodes[] = new Assignment(
                Path::fromSegments(['--without']),
                new Value('', ValueKind::Json, array_map(static fn (Path $path): string => implode('.', $path->segments()), $removals), true),
                [],
                null,
                $span,
            );
        }

        $section = [];
        foreach ($differences->all() as $difference) {
            if (!in_array($difference->kind(), [DifferenceKind::Added, DifferenceKind::Changed, DifferenceKind::MetadataChanged], true)) {
                continue;
            }

            $assignment = $difference->target();
            $segments = $assignment->path()->segments();
            $owner = count($segments) > 1 ? array_slice($segments, 0, -1) : [];

            if ($owner !== [] && $owner !== $section) {
                $nodes[] = new Section(Path::fromSegments($owner), implode('.', $owner), $assignment->span());
            }

            $section = $owner;

            foreach ($assignment->comments() as $comment) {
                $nodes[] = $comment;
            }

            $nodes[] = $assignment;
        }

        return $nodes;
    }

    /** @return list<Diagnostic> */
    private function diagnostics(DifferenceSet $differences): array
    {
        $diagnostics = [];
        foreach ($differences->all() as $difference) {
            $assignment = $difference->target();
            if ($assignment === null || $assignment->value()->isStaticallyKnown()) {
                continue;
            }

            $diagnostics[] = new Diagnostic(
                'jin.diff.opaque-copy',
                Severity::Info,
                sprintf('Copied unevaluated expression for %s verbatim.', $assignment->path()->toJsonPointer()),
                $assignment->path(),
                $assignment->span(),
                'Confirm the expression resolves identically from the generated file.',
            );
        }

        return $diagnostics;
    }

    private function guardOpaqueOverrides(ComposedDocument $parent, ComposedDocument $target): void
    {
        $conflicts = [];
        foreach ($this->assignments($parent) as $ancestor) {
            if ($ancestor->value()->isStaticallyKnown()) {
                continue;
            }

            foreach ($this->assignments($target) as $descendant) {
                if ($this->isAncestor($ancestor->path()->segments(), $descendant->path()->segments())) {
                    $conflicts[] = new CompositionConflict($ancestor->path()->toJsonPointer(), $descendant->path()->toJsonPointer());
                }
            }
        }

        if ($conflicts !== []) {
            throw new UndiffableDefinitionException($conflicts);
        }
    }

    /** @return list<Assignment> */
    private function assignments(ComposedDocument $document): array
    {
        $assignments = [];
        foreach ($document->document()->statements() as $statement) {
            if ($statement instanceof Assignment && !str_starts_with($statement->path()->segments()[0], '--')) {
                $assignments[] = $statement;
            }
        }

        return $assignments;
    }

    /**
     * @param list<string> $ancestor
     * @param list<string> $descendant
     */
    private function isAncestor(array $ancestor, array $descendant): bool
    {
        return count($descendant) > count($ancestor) && array_slice($descendant, 0, count($ancestor)) === $ancestor;
    }
}
