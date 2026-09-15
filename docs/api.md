# JinDistill API Reference

Everything here is public API under semver. Result objects are immutable.

## Facade — `JinDistill\JinDistiller`

| Method | Returns | Executes Jin? |
| --- | --- | --- |
| `analyzeFile(string $path)` | `Analysis\AnalysisResult` | no |
| `normalizeFile(string $path, ?FormatOptions $format = null)` | `Formatting\NormalizeResult` | no |
| `flattenFile(string $path, ?FormatOptions $format = null)` | `Composition\FlattenResult` | no |
| `diffFiles(string $parentPath, string $targetPath, string $outputPath, ?DiffOptions $options = null, ?FormatOptions $format = null, ?string $applicationRoot = null)` | `Diff\DiffResult` | no |
| `decode(string $contents, ?string $path = null)` | `Syntax\Document` | no |
| `decodeFile(string $path)` | `Syntax\Document` | no |
| `resolveFile(string $path)` | `JinDocument` | no |
| `normalize(string $contents, ?string $path = null, bool $extensions = true)` | `string` | no |
| `evaluateFile(string $path, ?EvaluationOptions $options = null)` | `Analysis\AnalysisResult` | **yes** |
| `verifySemantics(string $original, string $generated, ?EvaluationOptions $options = null)` | `Evaluation\VerificationResult` | **yes** |

No facade method writes a file. `diffFiles()` takes `$outputPath` only to
compute the `--extends` reference relative to where you intend to save.

## Deprecated output API

Encoder, OutputInterface, FileOutput, and StdOutput are deprecated as of 1.0.0.
They remain available through 1.x. Use a facade workflow, inspect returned
content(), then explicitly persist or echo that content.

## Results

### `Analysis\AnalysisResult`

| Accessor | Notes |
| --- | --- |
| `mode()` | `AnalysisMode::SourceOnly` or `AnalysisMode::Evaluated` |
| `sourceGraph()` | `documents(): array<string, Document>`, `edges(): list<SourceGraphEdge>` |
| `diagnostics()` | parser-level diagnostics |
| `provenance()` | `ProvenanceIndex` |
| `resolvedData()` | evaluated mode only; throws `LogicException` otherwise |

### `Formatting\NormalizeResult`

`content()`, `analysis()`, `diagnostics()`.

### `Composition\FlattenResult`

`content()`, `analysis()`, `provenance()`, `diagnostics()`.

### `Diff\DiffResult`

`content()`, `differences()`, `removals()`, `provenance()`, `diagnostics()`,
`verification()`.

`verification()` is `null` unless `DiffOptions::withVerification(true)` was
passed. It returns a `VerificationResult` proving statically that the parent,
the planned `--without` removals, and the generated assignments describe the
same configuration as the target.

### `Analysis\ProvenanceIndex`

`lineage(Path $path): ?Lineage` and `lineages(): list<Lineage>`. A `Lineage`
exposes `winner()`, `overridden()`, and `removals()`, each a `Definition` with
`path()`, `source()`, and `span()`.

## Options

### JinDistiller roots

JinDistiller instances are immutable with respect to filesystem resolution.
withApplicationRoot() sets the root used for file(...) inheritance and IMARC
diff references. withAllowedRoots() sets the filesystem allowlist. Both return
a new instance; the original instance remains unchanged.

### `Formatting\FormatOptions`

Immutable. `withIndentation()`, `withCommentPolicy()`, `withOrdering()`,
`withExtendsPathStyle()`. Profiles: `Profiles\ImarcStyle::v1()` (tabs, LF,
Hiraeth `file()` extends) and `Profiles\DotinkStyle::v1()` (bare relative
extends). See `docs/formatting.md`.

### `Diff\DiffOptions`

`metadataSensitive()` (default true — comment changes count as differences) and
`verifies()` (default false). Both have `with*()` counterparts.

### `Evaluation\EvaluationOptions`

`context()`, `functions()`, `associative()` — passed straight to Dotink's
parser. Register `file` here when the source uses Hiraeth `file()` inheritance.

### `Reporting\ReportOptions`

`includeValues()` — default false. See `docs/report-schema-v1.md`.

### `Analysis\AnalysisLimits`

Constructor arguments `bytes`, `syntaxDepth`, `inheritanceDepth`, and
`diagnostics`, with defaults in the class constants. Exceeding one throws
`Exceptions\AnalysisLimitException`. Pass limits to `Analyzer` and
`SourceGraphBuilder`.

## Sources and resolvers

| Class | Purpose |
| --- | --- |
| `Source\FilesystemSourceLoader` | loads files, restricted by `Source\PathPolicy` roots |
| `Source\MemorySourceLoader` | loads in-memory `LoadedSource` objects |
| `Source\RelativeExtendsResolver` | resolves bare relative `--extends` |
| `Source\FunctionExtendsResolver` | resolves `file(...)`-style `--extends` against a root |

`PathPolicy` rejects any load outside the configured roots, so inheritance
cannot escape the directory you allowed.

## Diagnostics

`Diagnostics\Diagnostic` exposes `ruleId()`, `severity()`, `message()`,
`path()`, `span()`, `suggestion()`, and `toArray()`.

| Rule ID | Default severity | Meaning |
| --- | --- | --- |
| `jin.style.duplicate-path` | error | the same path is assigned twice in one file |
| `jin.style.canonical-layout` | warning | statement layout differs from its canonical rendering |
| `jin.style.section-reference` | warning | relative `[&.section]` reference |
| `jin.style.line-ending` | warning | CRLF line endings |
| `jin.style.extends-function` | warning | `file()` inheritance with no registered `file` function |
| `jin.diff.opaque-copy` | info | a generated diff copied an unevaluated expression verbatim |

`Validation\ValidationRules::imarcV1()` carries the defaults;
`withSeverity()` and `withMaxDiagnostics()` return new instances.

## Exceptions

| Exception | Raised when |
| --- | --- |
| `InvalidStructureException` | the source is not valid Jin |
| `CircularInheritanceException` | `--extends` forms a cycle |
| `UnflattenableDefinitionException` | a nested override sits under an opaque expression |
| `UndiffableDefinitionException` | a diff target nests below an opaque parent assignment |
| `UnrepresentableValueException` | evaluated data contains a type Jin cannot express |
| `InvalidPathException` | an extends reference escapes the application root |
| `AnalysisLimitException` | a configured analysis limit is exceeded |

`UnflattenableDefinitionException` and `UndiffableDefinitionException` expose
`conflicts()`, each with `parentPath()` and `childPath()` JSON Pointers.
