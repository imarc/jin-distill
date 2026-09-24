# JinDistill Static Sync Lock

## Status

Implementation present on `rebuild` in commit `890f6d6`. Version 2.0 release
remains pending approval. This document does not authorize a release, tag, or
automatic lock-file write.

## Purpose

A caller needs to know whether the effective definitions of one or more Jin
configuration files changed since its last successful sync. The comparison
must show added, updated, and removed values without executing Jin functions.
It must also distinguish changes that only add values from changes that alter
or remove existing values.

This is a **static definition** comparison, not a comparison of runtime
resolved values. For example, unchanged `env("NAME")` source remains unchanged
in the lock even if the environment changes.

## Goals

- Capture a versioned, readable snapshot for a named set of root Jin files.
- Include statically composable inherited definitions and `--without` effects.
- Compare values, not formatting, comments, or assignment order.
- Report exact old and new values at stable paths.
- Classify each root and the whole set as unchanged, additive, removed,
  updated, or mixed.
- Provide source file and line when their attribution is reliable.
- Keep all capture and comparison work non-executing and read-only.

## Non-goals

- Evaluating `env()`, `run()`, `file()`, or caller-defined Jin functions.
- Proving runtime semantic equivalence or that an additive change is safe for
  an application. Ordered lists may affect application behavior even when
  appended to.
- Tracking comments, layout, formatting profile, or dependency file changes
  that leave effective definitions unchanged.
- Guessing keys produced inside opaque expressions.
- Managing update jobs, sync jobs, deployment state, or automatic lock writes.
- A CLI, separate package, or changes to the existing diff output contract.

## Placement and Public Interface

The feature belongs in JinDistill as a source-only snapshot and comparison
module. It reuses `Analyzer`, `DefinitionComposer`, syntax values, and source
spans. `DefinitionDiffer` compares assignments for generated Jin diffs; this
feature needs nested value and list comparison, so it must not reinterpret
`DefinitionDiffer` results as lock changes.

Proposed minimal interface:

```php
use JinDistill\Sync\StaticSnapshot;

$current = $distiller->snapshotFiles([
    'forms' => 'config/forms.jin',
    'mail' => 'config/mail.jin',
]);

$previous = StaticSnapshot::fromJson($lockContents);
$comparison = $previous->compare($current);

$comparison->status();  // unchanged, additive, removed, updated, or mixed
$comparison->changes(); // per-root paths, old/new values, optional locations
$current->toJson();     // content caller may persist after successful sync
```

`snapshotFiles()` accepts a non-empty map of stable logical root names to
paths. Reject empty names and unreadable or invalid sources. A PHP map cannot
represent duplicate names.
Logical names, not machine-specific absolute paths, identify roots across
snapshots. Existing `withApplicationRoot()` and `withAllowedRoots()` rules still
apply to source loading and inheritance. No method writes a file. Result
objects are immutable.

The caller decides where the lock lives and advances it only after downstream
sync succeeds. Missing or invalid locks are errors or explicit first-run
initialization; neither is silently treated as an empty snapshot.

## Snapshot Values

For each root, compose its source graph in parent-to-child order using the
existing static composition rules. Directives affect composition but do not
appear as values. Exclude comments, source ordering, and formatting options
from equality. Build a nested value tree from composed assignments and their
path segments: a dotted assignment and an equivalent nested literal must not
be compared as unrelated top-level assignments. Preserve literal map-key
segments, including keys containing dots or JSON Pointer escape characters.

Store values as a typed tree:

- Scalars retain type: null, boolean, integer, float, or string. Compare types
  and values strictly. Equivalent numeric or quoting spellings compare equal.
- Maps compare by key and value, regardless of source key order.
- Lists compare in order. Index is position, not stable item identity.
- Opaque expressions use their unevaluated value text. Different text counts
  as updated even if execution might produce the same value. Unchanged text
  cannot prove unchanged runtime output.

The serialized format must distinguish an empty map from an empty list and a
missing key from a key whose value is null. PHP's associative JSON decode and
`array_is_list([])` alone cannot preserve the first distinction. Add type
information at capture if necessary; do not approximate it during comparison.
Reject values that cannot be represented without changing their type or value.

Canonical serialization sorts map keys, preserves list order, and has a
versioned schema identifier. Store literal and opaque values in readable form;
do not require fingerprints or HMACs for this source-only workflow. Lock files
are still caller-owned data and must not be treated as trusted input without
schema validation.

Version 1 JSON has `schema: "jin-distill-static-sync-lock/1"` and a `roots`
object keyed by logical name. Each root is a typed node. Node types are
`null`, `boolean`, `integer`, `float`, `string`, `map`, `list`, and `opaque`.
Scalar and opaque nodes carry `value`; maps carry `entries` keyed by literal
Jin key; lists carry `items` in order. A root can also carry optional
`locations` metadata keyed by JSON Pointer. Locations do not participate in
equality. For example:

```json
{
  "schema": "jin-distill-static-sync-lock/1",
  "roots": {
    "forms": {
      "type": "map",
      "entries": {
        "enabled": {"type": "boolean", "value": true},
        "source": {"type": "opaque", "value": "env(\"FORM_SOURCE\")"}
      }
    }
  }
}
```

## Comparison Rules

Changes use JSON Pointer paths within each logical root. Escape `~` and `/`
per RFC 6901. Each change has a kind (`added`, `updated`, or `removed`), path,
and applicable old and new typed values. Distinguish absence from null.

For maps, compare keys recursively. A new or missing map key produces one
change at that key, with its entire subtree as the value. Do not also report
every descendant. A type change at an existing path is one update at that
path. A changed scalar or opaque expression is an update at that path.

For lists:

| Old | New | Change |
| --- | --- | --- |
| `[a, b]` | `[a, b, c]` | added item `c` at `/2`; additive |
| `[a, b, c]` | `[a, b]` | removed item `c` at `/2`; removed |
| `[b, c]` | `[a, b, c]` | updated list; `b` and `c` shift |
| `[a, b, c]` | `[a, c]` | updated list; `c` shifts |
| `[a, b]` | `[a, x]` | updated list |

Only an exact old-prefix/new-list relation with a non-empty new suffix counts
as appended items. Only an exact new-prefix/old-list relation with a non-empty
old suffix counts as tail removals. Emit one change per suffix item. Compare
prefix items as typed trees, ignoring map key order. All other list changes produce
one update at the list path with complete old and new lists; do not infer item
identity from duplicates or a longest-common-subsequence heuristic. An empty
list can gain or lose tail items under the same rule. Adding or removing the
entire list key is a key addition or removal, not an item-level change.

Per-root and aggregate status use the same rule: no changes means `unchanged`;
only additions means `additive`; only removals means `removed`; only updates
means `updated`; more than one change kind means `mixed`. An added or removed
logical root counts as one addition or removal with its complete value tree.
Root names must not change silently: a rename appears as a removal plus an
addition.
Order changes deterministically by logical root, map key, then list index.

## Source Locations

Capture source spans as optional metadata, excluded from equality. For a
direct assignment, report the assignment's original file and line. For a
nested literal, report a member line only when the decoder can attribute that
member reliably; otherwise report the containing assignment as such or omit
the location. For inherited values, use the actual parent or child definition
that supplied the value. Never label a merged parent value as child-owned
merely because the composed assignment carries the child's span.

Current locations come from the current capture. Removed values may carry a
snapshot-time location from the previous lock; label it as previous location
because the source file may have moved since capture. A `--without` directive
may supply a current removal location if it is reliably linked to that path.
Unavailable locations do not prevent a value comparison.

## Failure and Safety Rules

- No capture or comparison path calls `JinEvaluator`, `DotinkEvaluator`, or
  `SemanticVerifier`.
- Missing files, invalid syntax, unsupported lock schema, and malformed lock
  data fail explicitly; never return `unchanged` or `additive` on incomplete
  input.
- If a nested override requires understanding an opaque expression,
  preserve `DefinitionComposer`'s refusal. Do not fabricate a snapshot or
  classify the result as additive.
- If an opaque expression itself changes, report its assignment as updated.
  Do not inspect the expression's possible runtime children.
- The lock is data supplied by a caller. Deserialization validates shape,
  types, sizes, and nesting before creating a snapshot.
- Serialize deterministically so repeated capture with unchanged effective
  definitions produces identical value content. Optional locations may change
  after source-only moves; they never change comparison status.

## Acceptance Cases

- A child changes an inherited literal: update at the effective path, with
  previous and current values.
- A parent changes a value that a child overrides: unchanged for that child.
- A parent adds an inherited key: additive for that child.
- A child removes an inherited key with `--without`: removed.
- Layout and comments outside value bodies, map key order, and equivalent
  literal spellings: unchanged. Changed opaque expression text, including its
  internal whitespace, is updated. Changing only its external environment
  without source changes is unchanged by the static contract.
- Appending to a list: additive. Truncating its tail: removed. Prepending,
  inserting, deleting from the middle, replacing, or reordering: updated.
- Adding a new logical root: additive. Removing one: removed. Combining an
  addition with an update or removal: mixed.
- Null versus absent, empty map versus empty list, and integer versus string:
  distinct values.
- Invalid or partially unreadable input never advances the lock or returns a
  clean status. `toJson()` does not itself write or advance a lock.
