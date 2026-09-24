# Static Sync Lock Implementation Plan

**Status:** Historical handoff plan. Implementation landed in `890f6d6`.
Checklists below record intended steps, not a verified completion report.

**Spec:** [Static Sync Lock](../specs/2026-09-23-static-sync-lock-design.md).

**Outcome:** A caller captures 20 named root `.jin` files in one readable lock
snapshot, later captures the same set, and receives per-root and aggregate
change classifications with old/new values. Capture never evaluates Jin or
writes a lock file. The caller advances the lock after its own sync succeeds.

## Constraints and handoff

- Work on `rebuild`. Preserve existing modified, staged, and untracked files;
  they predate this feature. Do not overwrite the three user-owned untracked
  files in `examples/diff/` or disturb the staged `CsvFormat.php` change.
- Follow repository `AGENTS.md`: prefix shell commands with `rtk`, use
  `apply_patch` for edits, match nearby PHP style, and ask the user before
  writing or committing test files. Do not commit, push, merge, or tag without
  explicit authorization. Do not spawn subagents unless asked.
- The spec in `docs/specs/` is trackable. `docs/superpowers/` is gitignored;
  do not move this plan there.
- Keep the public interface small. No storage adapter, CLI, hash field, runtime
  evaluation mode, or change to `ReportSerializer` V1.
- Before changing composition or decoding, run focused existing tests and
  inspect their behavior. Fix a shared invariant at its owner rather than
  creating separate sync-only merge semantics.

## Task 1: Prove the static input model

**Likely files:** `src/JinDistill/Decoders/JinDecoder.php`,
`src/JinDistill/Syntax/Value.php`, `src/JinDistill/Composition/DefinitionComposer.php`,
`src/JinDistill/Analysis/SourceGraphBuilder.php`; focused tests only after
the required approval.

- [ ] Inspect existing scalar, JSON-like map/list, multiline, opaque,
  `--extends`, and `--without` handling. Establish exact composed-assignment
  behavior for dotted paths and nested literal maps.
- [ ] Preserve map/list shape through decoding and composition, including
  `{}` versus `[]`, nested empty containers, and numeric-looking map keys.
  `json_decode(..., true)` and `array_is_list([])` lose required information;
  retain the shape at the responsible syntax/composition layer. Keep existing
  `staticValue()` consumers compatible. Do not add a second Jin parser.
- [ ] Make snapshot capture reject unresolved/dynamic `--extends` or
  `--without`. Today an unsupported extends resolver can leave an edge
  unvisited, and an opaque `--without` can be ignored. A lock must not claim
  completeness in either case. Preserve current static workflows unless a
  shared correctness fix is justified and covered by regression proof.
- [ ] Retain `DefinitionComposer`'s explicit failure for nested changes below
  opaque assignments. Do not evaluate opaque functions or guess their keys.

**Checkpoint:** Layout changes outside opaque value bodies leave the static
tree unchanged. Empty map/list types survive capture. An incomplete
inheritance graph cannot yield a snapshot.

## Task 2: Capture one named set as one immutable snapshot

**Likely files:** `src/JinDistill/JinDistiller.php` and a small
`src/JinDistill/Sync/` module; approved tests under `tests/Sync/`.

- [ ] Add `JinDistiller::snapshotFiles(array $files): StaticSnapshot`. Accept
  a non-empty logical-name-to-path map. Reject empty names, missing paths,
  invalid syntax, and unreadable dependencies. Use existing
  `analyzerForFile()` so `withApplicationRoot()` and `withAllowedRoots()`
  continue to apply. Each named root is captured independently; inherited
  parents need not be separately named.
- [ ] Verify both absolute and nested relative root paths. In the current
  facade, `analyzerForFile()` derives a root from `dirname($path)` while the
  loader resolves relative paths from that root; canonicalize supplied roots
  before analysis or fix the shared path handling if focused tests confirm a
  double-prefix error.
- [ ] For each root, compose inherited definitions, then build one nested
  typed value tree. Apply dotted assignment paths by segments, not by joining
  keys with dots. Preserve literal JSON member names such as `a.b`, `~`, and
  `/`. Exclude directives, comments, formatting, and source order from value
  equality. Opaque expressions are tagged with their unevaluated value text.
- [ ] Keep root names stable across machines. A missing source path is an
  error during capture; an omitted logical name in a valid later capture is a
  root removal during comparison. Never confuse these cases.
- [ ] Capture optional source locations only where ownership is reliable.
  Record assignment source and line; use a nested member line only when the
  decoder can prove it. For inherited or merged JSON values, do not attach a
  child's span to parent-owned values. Use stable root-relative source paths
  when possible; omit uncertain or nonportable locations rather than report
  false ones.

**Checkpoint:** One snapshot contains multiple named roots. A parent change
hidden by a child override does not change that child's tree; an inherited
new value does. No evaluator or output file is touched.

## Task 3: Versioned, validated lock serialization

**Likely files:** `src/JinDistill/Sync/StaticSnapshot.php` and a private codec
if it earns its keep; approved serialization tests.

- [ ] Implement `StaticSnapshot::toJson()` and `StaticSnapshot::fromJson()`
  for spec schema `jin-distill-static-sync-lock/1`. Use typed nodes for null,
  boolean, integer, float, string, map, list, and opaque. Preserve exact types,
  including null versus absent and float versus integer. Sort logical root
  names and map keys; preserve list order. Locations are optional and separate
  from values.
- [ ] Validate all untrusted lock input: schema, node shape, scalar types,
  root names, nesting depth, size, and location structure. Reject unknown
  node types, malformed JSON, non-finite numbers, and values that cannot
  round-trip exactly. Reuse existing analysis limits where they fit; keep any
  lock-only limit narrow and documented.
- [ ] Ensure serialization is deterministic. Round-tripping a snapshot must
  preserve comparison results. Metadata-only source moves may change
  serialized locations but not value equality.
- [ ] Keep disk I/O out of this module. Caller reads lock contents and writes
  `toJson()` after successful downstream sync. No implicit first-run empty
  snapshot and no stored per-root hash.

**Checkpoint:** One JSON lock stores all roots and can be parsed on another
machine. Invalid or unsupported locks fail, never compare as unchanged.

## Task 4: Compare typed trees and classify changes

**Likely files:** `src/JinDistill/Sync/StaticSnapshot.php`, immutable comparison
and change result types in `src/JinDistill/Sync/`; approved comparison tests.

- [ ] Implement `$previous->compare($current)` with a value-only equality
  fast path per root. No persisted hash: capture and hashing would both visit
  the full tree. If equal, skip detailed change allocation for that root.
- [ ] Compare map keys recursively. Emit one added or removed change for a
  whole new/missing subtree; emit one update for a type or scalar change.
  Use RFC 6901 JSON Pointers and distinguish missing from present null.
- [ ] Compare lists by position. Exact append emits one added change per new
  tail item; exact tail truncation emits one removed change per old tail item.
  Prepend, middle insertion/deletion, replacement, and reorder emit one update
  at the list path with complete old/new lists. Do not infer identity with a
  sequence-diff heuristic. `[b, c]` to `[a, b, c]` is updated.
- [ ] Handle new/missing logical roots as one addition/removal with their
  complete tree. Order changes deterministically. A root and the whole set
  report `unchanged`, `additive`, `removed`, `updated`, or `mixed` from the
  change kinds. Locations never affect status.
- [ ] Return immutable structured changes containing root name, kind,
  pointer, applicable old/new typed values, and optional previous/current
  location. Previous locations are explicitly snapshot-time locations.

**Checkpoint:** All spec list examples and aggregate mixed cases classify
correctly. Formatting-only changes do not produce changes; opaque expression
text changes do.

## Task 5: Public documentation and proof

**Likely files:** `README.md`, `docs/api.md`, `docs/security.md`, and approved
task-specific tests. Do not alter unrelated pending 2.0 formatting work.

- [ ] Document a 20-file example that writes one lock file only in caller
  code, compares a later capture, and advances the file only after sync
  succeeds. State that a lock compares static definitions, not runtime values.
- [ ] Document exact status and list rules, optional location precision,
  source restrictions, and failure modes. State that `env()` output changes
  alone are invisible. Explain why there is no hash or CLI in version 1.
- [ ] After permission to write tests, cover every spec acceptance case,
  especially inherited override/removal, equivalent literals, empty map/list,
  numeric-looking map keys, opaque expressions, list edge cases, multi-root
  changes, invalid locks, missing files, and non-execution. Use existing
  PHPUnit conventions and source fixtures; do not touch user-owned examples.
- [ ] Run focused tests after each task and final `rtk composer check`, then
  `rtk git diff --check` and `rtk git status --short`. Report exact results and
  remaining risks. Do not stage, commit, push, merge, or tag.

**Done when:** The public interface can capture, serialize, reload, and
compare one set of named roots without evaluation or file writes; every
specified change class and failure mode is covered; documentation matches
behavior; repository checks pass. Stop there.
