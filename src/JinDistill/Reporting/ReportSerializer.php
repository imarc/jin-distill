<?php

namespace JinDistill\Reporting;

use InvalidArgumentException;
use JinDistill\Analysis\AnalysisMode;
use JinDistill\Analysis\AnalysisResult;
use JinDistill\Analysis\Lineage;
use JinDistill\Analysis\ProvenanceIndex;
use JinDistill\Composition\FlattenResult;
use JinDistill\Diagnostics\Diagnostic;
use JinDistill\Diff\DiffResult;
use JinDistill\Diff\Difference;
use JinDistill\Evaluation\VerificationResult;
use JinDistill\Formatting\NormalizeResult;
use JinDistill\Source\Path;

/**
 * Serializes immutable JinDistill results into the stable version 1 report
 * schema documented in docs/report-schema-v1.md.
 */
final class ReportSerializer
{
    /** @return array<string, mixed> */
    public function toArray(object $result, ?ReportOptions $options = null): array
    {
        $options ??= new ReportOptions();

        return match (true) {
            $result instanceof AnalysisResult => $this->analysis($result, $options),
            $result instanceof NormalizeResult => $this->normalize($result, $options),
            $result instanceof FlattenResult => $this->flatten($result, $options),
            $result instanceof DiffResult => $this->diff($result),
            default => throw new InvalidArgumentException(sprintf('Unsupported JinDistill result: %s', $result::class)),
        };
    }

    public function toJson(object $result, ?ReportOptions $options = null): string
    {
        return json_encode($this->toArray($result, $options), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /** @return array<string, mixed> */
    private function analysis(AnalysisResult $analysis, ReportOptions $options, string $type = 'analysis', ?string $content = null): array
    {
        $report = [
            'schema' => SchemaVersion::V1,
            'type' => $type,
            'mode' => $analysis->mode() === AnalysisMode::Evaluated ? 'evaluated' : 'source-only',
            'sources' => $this->sources($analysis),
            'edges' => $this->edges($analysis),
            'diagnostics' => $this->diagnostics($analysis->diagnostics()),
            'lineage' => $this->lineage($analysis->provenance()),
        ];

        if ($content !== null) {
            $report['content'] = $content;
        }

        if ($analysis->mode() === AnalysisMode::Evaluated) {
            $report['values'] = $this->values($analysis->resolvedData(), $options);
        }

        return $report;
    }

    /** @return array<string, mixed> */
    private function normalize(NormalizeResult $result, ReportOptions $options): array
    {
        $report = $this->analysis($result->analysis(), $options, 'normalize', $result->content());
        $report['diagnostics'] = $this->diagnostics($result->diagnostics());

        return $report;
    }

    /** @return array<string, mixed> */
    private function flatten(FlattenResult $result, ReportOptions $options): array
    {
        $report = $this->analysis($result->analysis(), $options, 'flatten', $result->content());
        $report['lineage'] = $this->lineage($result->provenance());
        $report['diagnostics'] = $this->diagnostics($result->diagnostics());

        return $report;
    }

    /** @return array<string, mixed> */
    private function diff(DiffResult $result): array
    {
        return [
            'schema' => SchemaVersion::V1,
            'type' => 'diff',
            'mode' => 'source-only',
            'content' => $result->content(),
            'differences' => $this->differences($result),
            'removals' => array_map(static fn (Path $path): string => $path->toJsonPointer(), $result->removals()),
            'diagnostics' => $this->diagnostics($result->diagnostics()),
            'lineage' => $this->lineage($result->provenance()),
            'verification' => $this->verification($result->verification()),
        ];
    }

    /** @return array<string, list<string>> */
    private function differences(DiffResult $result): array
    {
        $differences = ['added' => [], 'changed' => [], 'removed' => [], 'metadata-changed' => []];

        foreach ($result->differences()->all() as $difference) {
            $differences[$difference->kind()->value][] = $difference->path()->toJsonPointer();
        }

        return $differences;
    }

    /** @return list<array<string, string>> */
    private function sources(AnalysisResult $analysis): array
    {
        $sources = [];
        foreach ($analysis->sourceGraph()->documents() as $document) {
            $sources[] = [
                'path' => $document->source()->canonicalPath(),
                'reference' => $document->source()->reference(),
            ];
        }

        return $sources;
    }

    /** @return list<array<string, string>> */
    private function edges(AnalysisResult $analysis): array
    {
        return array_map(static fn ($edge): array => [
            'child' => $edge->child()->canonicalPath(),
            'parent' => $edge->parent()->canonicalPath(),
            'reference' => $edge->reference(),
        ], $analysis->sourceGraph()->edges());
    }

    /**
     * @param list<Diagnostic|object> $diagnostics
     * @return list<array<string, mixed>>
     */
    private function diagnostics(array $diagnostics): array
    {
        return array_map(static fn (object $diagnostic): array => $diagnostic->toArray(), $diagnostics);
    }

    /** @return list<array<string, mixed>> */
    private function lineage(ProvenanceIndex $provenance): array
    {
        return array_map(static fn (Lineage $lineage): array => [
            'path' => $lineage->winner()->path()->toJsonPointer(),
            'source' => $lineage->winner()->source()->canonicalPath(),
            'overridden' => array_map(static fn ($definition): string => $definition->source()->canonicalPath(), $lineage->overridden()),
            'removals' => array_map(static fn ($definition): string => $definition->source()->canonicalPath(), $lineage->removals()),
        ], $provenance->lineages());
    }

    /** @return list<array<string, mixed>> */
    private function values(mixed $data, ReportOptions $options, string $pointer = ''): array
    {
        if (!is_array($data)) {
            $entry = ['path' => $pointer === '' ? '/' : $pointer, 'type' => get_debug_type($data)];

            if ($options->includeValues()) {
                $entry['value'] = $data;
            }

            return [$entry];
        }

        $values = [];
        foreach ($data as $key => $nested) {
            foreach ($this->values($nested, $options, $pointer . '/' . str_replace(['~', '/'], ['~0', '~1'], (string) $key)) as $value) {
                $values[] = $value;
            }
        }

        return $values;
    }

    /** @return array<string, mixed>|null */
    private function verification(?VerificationResult $verification): ?array
    {
        if ($verification === null) {
            return null;
        }

        return ['equivalent' => $verification->isEquivalent(), 'differences' => $verification->differences()];
    }
}
