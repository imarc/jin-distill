# JinDistill Report Schema V1

`JinDistill\Reporting\ReportSerializer` converts immutable result objects into
this schema. `ReportSerializer::toJson()` encodes it with
`JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES` and preserves key order.

The schema version is `1.0` (`JinDistill\Reporting\SchemaVersion::V1`). Field
names, types, diagnostic rule IDs, and JSON Pointer semantics follow semver:
new optional keys may appear in a minor release, existing keys never change
meaning inside a major version.

## Supported results

| Result | `type` | Content |
| --- | --- | --- |
| `Analysis\AnalysisResult` | `analysis` | no `content` key |
| `Formatting\NormalizeResult` | `normalize` | canonical source of one file |
| `Composition\FlattenResult` | `flatten` | standalone composed source |
| `Diff\DiffResult` | `diff` | generated inheritance document |

Any other object raises `InvalidArgumentException`; the serializer never
reflects arbitrary values.

## Keys

| Key | Type | Present for | Notes |
| --- | --- | --- | --- |
| `schema` | string | all | always `1.0` |
| `type` | string | all | table above |
| `mode` | string | all | `source-only` or `evaluated`; diffs are always `source-only` |
| `content` | string | normalize, flatten, diff | rendered Jin source |
| `sources` | list | analysis, normalize, flatten | `{path, reference}` per analyzed document |
| `edges` | list | analysis, normalize, flatten | `{child, parent, reference}` inheritance edges, `reference` is the literal `--extends` expression |
| `diagnostics` | list | all | `{rule, severity, path, span, message, suggestion}` |
| `lineage` | list | all | `{path, source, overridden, removals}`; `path` is a JSON Pointer, the rest are canonical source paths |
| `differences` | object | diff | keys `added`, `changed`, `removed`, `metadata-changed`, each a list of JSON Pointers |
| `removals` | list | diff | JSON Pointers planned for `--without` |
| `verification` | object or null | diff | `{equivalent, differences}`; null unless `DiffOptions::withVerification(true)` |
| `values` | list | evaluated analysis only | see redaction |

Paths use RFC 6901 JSON Pointers: `/form/fields/birthDate`, with `~0` for `~`
and `~1` for `/`.

## Redaction

Evaluated analysis can contain secrets pulled from the environment or produced
by `run()`. By default every entry in `values` carries only `path` and `type`:

```json
{"path": "/database/password", "type": "string"}
```

`new ReportOptions(includeValues: true)` adds a `value` key. Callers opt in
explicitly, per report; nothing else in the schema exposes evaluated data.

Source-only results have no `values` key at all — analysis, normalization,
flattening, and diffing never execute Jin expressions.
