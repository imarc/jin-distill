# Changelog

All notable changes to this project are documented here. This project follows
[semantic versioning](https://semver.org/).

## [2.0.0] - unreleased

### Changed

- `FormatOptions` now uses typed presentation choices. `ImarcStyle::v2()` is
  the default profile; `DotinkStyle::v2()` uses bare extends paths. Explicit
  `v1()` profiles preserve 1.x output. Formatting options now apply to
  normalization, flattening, diff rendering, and canonical diagnostics.
- Diff generation now treats the target as local overrides rather than a
  complete replacement. Parent-only assignments remain inherited; changed
  JSON lists still receive assignment-scoped `--without` directives.
- Static overlay verification now proves this inheritance-preserving diff
  contract instead of exact equality with the standalone target.

### Added

- Numeric-token preservation for static assignments, objects, and lists;
  optional always-quoted strings and LF/CRLF output.
- Immutable application-root and allowed-root configuration on `JinDistiller`.
- Caller-provided runtime parser factories plus path-aware file verification.
- Configurable preservation of leading comments and blank lines through
  normalization and flattening.

### Fixed

- Preserve JSON-member metadata and section-leading trivia when flattening.
- Preserve assignment-leading trivia while applying nested `--without`
  directives.
- Render empty comments without trailing whitespace.
- Pass the CSV escape argument explicitly for PHP 8.4 compatibility.

### Migration from 1.x

- Replace `CommentPolicy` and `withCommentPolicy()` with
  `LeadingCommentPolicy` and `withLeadingComments()`.
- Use enum cases for line endings, numeric style, string quoting, and section
  references. `ExtendsPathStyle` constants now hold `InheritancePathStyle`
  enum cases; PHP cannot declare an enum with the old class name.
- Remove `diffGranularity` and `metadataSensitiveDiffs` from
  `FormatOptions` constructor calls. Diff remains assignment-level; use
  `DiffOptions::withMetadataSensitivity()` for comparison behavior.
- Use named constructor arguments or start with `ImarcStyle::v2()` and withers.
  Use explicit `ImarcStyle::v1()` or `DotinkStyle::v1()` where existing bytes
  must remain unchanged. No files are rewritten by this migration.

## [1.0.1] - 2026-09-15

### Deprecated

- Deprecated the legacy output API in favor of explicit persistence of facade
  workflow results.

## [1.0.0] - 2026-09-15

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
- Minimum removal planning, including collapsing an owner whose children are
  all removed.
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
