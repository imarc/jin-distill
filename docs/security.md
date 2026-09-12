# Security

## What executes and what does not

| Workflow | Executes Jin expressions |
| --- | --- |
| `analyzeFile()` | no |
| `normalizeFile()` | no |
| `flattenFile()` | no |
| `diffFiles()` | no |
| `evaluateFile()` | **yes** |
| `verifySemantics()` | **yes** |

The static workflows parse source into a syntax model. An expression such as
`run(...)` is kept as opaque text: compared as raw bytes, copied verbatim, and
never called. When a static override would have to reach *inside* an opaque
expression, JinDistill refuses with `UnflattenableDefinitionException` or
`UndiffableDefinitionException` rather than guessing.

## Why evaluation is dangerous

Dotink's parser resolves Jin functions while parsing:

- `run(...)` executes arbitrary PHP.
- `env(...)` reads process environment variables, which commonly hold
  credentials.
- `file(...)` and `--extends` read files from disk.
- Any function you register in `EvaluationOptions` runs with your privileges.

Evaluate only configuration you trust. Treat `evaluateFile()` on
attacker-controlled Jin as equivalent to running attacker-supplied PHP.

## Path restrictions

`Source\FilesystemSourceLoader` takes a `Source\PathPolicy` listing allowed
roots and refuses any load outside them, so `--extends` cannot walk out of the
directory you permitted. `Formatting\ExtendsReference` throws
`InvalidPathException` when a parent lies outside the configured application
root.

## Resource limits

`Analysis\AnalysisLimits` bounds source bytes, syntax depth, inheritance depth,
and collected diagnostics. Exceeding any of them throws
`AnalysisLimitException` before more work is done. Defaults are generous;
lower them when analyzing untrusted input.

## Report redaction

`Reporting\ReportSerializer` never emits evaluated values unless the caller
passes `new ReportOptions(includeValues: true)`. By default an evaluated report
carries only the JSON Pointer and the value's type:

```json
{"path": "/database/password", "type": "string"}
```

Source-only reports have no `values` key at all. Diagnostics and verification
results reference paths, never values.

## Semantic verification

`verifySemantics()` parses two sources with Dotink and compares the resolved
data. It is opt-in and executes both inputs, so run it only on trusted
configuration — typically to confirm that a normalized or flattened file still
resolves the way the original did. Inheritance directives (`--extends`,
`--without`) are excluded from the comparison because they describe
composition, not configuration.

## Reporting a vulnerability

Open a security advisory on the repository rather than a public issue.
