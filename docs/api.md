# JinDistill API Reference

Everything here is public API under semver. Result objects are immutable.

## Facade — `JinDistill\JinDistiller`

| Method | Returns | Executes Jin? |
| --- | --- | --- |
| `analyzeFile(string $path)` | `Analysis\AnalysisResult` | no |
| `normalizeFile(string $path, ?FormatOptions $format = null)` | `Formatting\NormalizeResult` | no |
| `flattenFile(string $path, ?FormatOptions $format = null)` | `Composition\FlattenResult` | no |
| `diffFiles(string $parentPath, string $targetPath, string $outputPath, ?DiffOptions $options = null, ?FormatOptions $format = null, ?string $applicationRoot = null)` | `Diff\DiffResult` | no |
| `snapshotFiles(array $files)` | `Sync\StaticSnapshot` | no |
| `decode(string $contents, ?string $path = null)` | `Syntax\Document` | no |
| `decodeFile(string $path)` | `Syntax\Document` | no |
| `resolveFile(string $path)` | `JinDocument` | no |
| `normalize(string $contents, ?string $path = null, bool $extensions = true)` | `string` | no |
| `evaluateFile(string $path, ?EvaluationOptions $options = null)` | `Analysis\AnalysisResult` | **yes** |
| `verifySemantics(string $original, string $generated, ?EvaluationOptions $options = null)` | `Evaluation\VerificationResult` | **yes** |
| `verifyFileSemantics(string $originalPath, string $generated, ?EvaluationOptions $options = null)` | `Evaluation\VerificationResult` | **yes** |

No facade method writes a file. `diffFiles()` takes `$outputPath` only to
compute the `--extends` reference relative to where you intend to save.

## Deprecated output API

Encoder, OutputInterface, FileOutput, and StdOutput are deprecated as of 1.0.0.
They remain available in 2.0 for migration. Use a facade workflow, inspect
returned content(), then explicitly persist or echo that content.

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
the assignment-scoped `--without` removals, and the generated overrides retain
the target's local values while preserving parent-only assignments.

This inheritance-preserving behavior is the 2.0 diff contract. Unlike 1.x,
parent-only assignments are not removed to make the generated overlay equal a
standalone target.

### `Sync\StaticSnapshot` and `Sync\StaticComparison`

`snapshotFiles()` accepts a non-empty map from stable logical names to readable
root paths. Each root composes its inherited definitions. Missing files,
invalid syntax, unresolved `--extends`, and dynamic `--without` fail capture.
`withApplicationRoot()` and `withAllowedRoots()` still govern source loading.

`StaticSnapshot::toJson()` returns a deterministic, readable lock with schema
`jin-distill-static-sync-lock/1`. `StaticSnapshot::fromJson(string $json)`
validates caller-owned lock data. Neither method reads or writes a file.
`$previous->compare($current)` returns `StaticComparison` with `status()`,
`rootStatuses()`, and `changes()`. No stored hash or CLI is needed: capture and
comparison already visit the values, and the caller owns lock persistence.

Status is `unchanged`, `additive`, `removed`, `updated`, or `mixed`. A new or
missing root is one change. Maps compare recursively by literal key; a new or
missing key is one whole-subtree change. Lists compare by position. Exact
append adds tail items; exact tail truncation removes tail items. Prepend,
middle insertion or removal, replacement, and reorder update the whole list.
Changes use RFC 6901 JSON Pointer paths within a root.

Each `StaticChange` has `root()`, `kind()`, `path()`, `previous()`, `current()`,
`previousLocation()`, and `currentLocation()`. Values are typed nodes: `null`,
`boolean`, `integer`, `float`, `string`, `map`, `list`, or `opaque`. A missing
value is `null` in an accessor; a present Jin null is `['type' => 'null']`.
Locations contain root-relative `source` and one-based `line` when attribution
is reliable. Previous locations belong to the old snapshot and may be stale.
Locations never affect status.

The lock compares source definitions, not resolved runtime values. Opaque
expressions compare by unevaluated text, so changing only an environment
variable does not produce a change. The lock rejects input over 32 MiB or
nesting deeper than 64 nodes.

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

`withEvaluator(JinEvaluator $evaluator)` configures an immutable evaluator for
`evaluateFile()`, `verifySemantics()`, and `verifyFileSemantics()`. Static
workflows never use it. Explicit per-call `EvaluationOptions` take precedence.

### `Formatting\FormatOptions`

Immutable. Constructor defaults match `Profiles\ImarcStyle::v2()`. Named
arguments, accessors, and withers use these types:

| Constructor argument | Type | Wither |
| --- | --- | --- |
| `indentation` | non-empty `string` | `withIndentation()` |
| `lineEnding` | `LineEnding` | `withLineEnding()` |
| `leadingComments` | `LeadingCommentPolicy` | `withLeadingComments()` |
| `ordering` | `OrderingPolicy` | `withOrdering()` |
| `extendsPathStyle` | `InheritancePathStyle` | `withExtendsPathStyle()` |
| `numericStyle` | `NumericStyle` | `withNumericStyle()` |
| `stringQuoting` | `StringQuoting` | `withStringQuoting()` |
| `sectionReferences` | `SectionReferenceStyle` | `withSectionReferences()` |
| `spacingPolicy` | `SpacingPolicy` | `withSpacingPolicy()` |

Each accessor uses the argument name, for example `lineEnding()`.
`ExtendsPathStyle::BareRelative` and `::HiraethFile` remain enum-valued
compatibility constants; PHP cannot name an enum `ExtendsPathStyle`.
`ImarcStyle::v1()` and `DotinkStyle::v1()` remain available for byte-stable
1.x output. See `docs/formatting.md` for option behavior.

### `Diff\DiffOptions`

`metadataSensitive()` (default true — comment changes count as differences) and
`verifies()` (default false). Both have `with*()` counterparts.

### `Evaluation\EvaluationOptions`

`context()`, `functions()`, `associative()` — passed straight to Dotink's
parser. Register `file` here when the source uses Hiraeth `file()` inheritance.

### `Evaluation\JinEvaluator`

Construct with a callable that returns a fresh `Dotink\Jin\Parser`. This lets an
application reuse its runtime parser configuration without sharing mutable
parser state between evaluations. `fromOptions()` provides the default adapter.

`verifyFileSemantics()` reads the original file and supplies its path to the
runtime parser before comparing it with generated source.

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
