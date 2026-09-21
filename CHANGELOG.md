# Changelog

All notable changes to this project are documented here. This project follows
[semantic versioning](https://semver.org/).

## [1.0.0] - unreleased

First stable release. Everything before 1.0 is replaced outright, not
deprecated: the pre-1.0 array-shaped API has been superseded by the syntax
model, immutable result objects, and the facade described in `docs/api.md`.

### Analysis

- Source graph construction with cycle detection and path-restricted loading.
- Per-path provenance: the winning definition, everything it overrode, and
  every `--without` that removed it.
- Configurable limits for bytes, syntax depth, inheritance depth, and
  diagnostic count, plus an opt-in in-memory analysis cache.

### Formatting and validation

- Immutable `FormatOptions` with frozen `ImarcStyle::v1()` and
  `DotinkStyle::v1()` profiles.
- Ordered syntax renderer that preserves comments, order, function bodies, and
  multiline values while normalizing layout, numbers, and quoting.
- Rule-based validation reporting duplicate paths, noncanonical layout,
  relative section references, CRLF line endings, and unregistered `file()`
  inheritance, each with a suggested fix.
- Source-local normalization that never rewrites parents and never evaluates.

### Composition

- Static flattening of an inheritance chain into one standalone document,
  including `--without` removals nested inside static objects.
- Typed refusal when a static override would have to reach inside an opaque
  runtime expression.

### Diffing

- Assignment-level differences classified as added, changed, removed, or
  metadata-changed.
- Assignment-scoped removal planning for changed JSON lists, preserving sibling
  values added to an extended parent.
- Generated inheritance documents with canonical extends references, planned
  `--without`, target comments, and canonical sectioning.
- Optional static overlay verification proving the generated document resolves
  like the target, without executing anything.

### Evaluation

- Explicit, clearly labelled Dotink evaluation and semantic verification for
  trusted configuration only.

### Reporting

- Versioned V1 report schema for analysis, normalization, flatten, and diff
  results, with evaluated values redacted unless the caller opts in.

### Fixed

- Strings are now escaped the way Jin reads them (doubled quotes). The previous
  JSON-style backslash escaping produced files Dotink could not parse.
- The legacy array formatter no longer stringifies top-level lists as `Array`.
