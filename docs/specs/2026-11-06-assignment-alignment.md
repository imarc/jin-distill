# Optional assignment alignment

## API and compatibility

Add `FormatOptions::withAssignmentAlignment(bool): self` and
`FormatOptions::assignmentAlignment(): bool`. The option is disabled by default
in every profile. The setter returns a clone, matching the existing options.

```php
$format = ImarcStyle::v2()->withAssignmentAlignment(true);
```

The option controls both assignment equals signs and object property colons:

```jin
class    = Payment
email    = emails.dues
template = @emails/payments/firm-paid-dues.html

options = {
    "class"    : "Payment",
    "email"    : "emails.dues",
    "template" : "@emails/payments/firm-paid-dues.html",
}
```

Normalization, flattening, and generated diff content use the same option.
Default output, including the v1 profiles, remains byte-for-byte unchanged.
No parser, evaluation, composition, diff comparison, or lock schema changes
are required.

## Grouping and padding

- Group consecutive eligible assignments in the same rendered section.
- Group eligible properties independently within each object. Objects nested
  in objects or lists use their own groups. List elements are not aligned.
- Determine groups after applying statement ordering and blank-line policy.
  Only blank lines that are emitted break groups. Removing blank lines with
  `SpacingPolicy::None` joins adjacent eligible rows immediately, preserving
  idempotence.
- Emitted comments, section headers, and multiline values break groups.
  A row with an inline comment is excluded and breaks the group on both sides.
- File-level directives are excluded and break groups on both sides.
- Aligned output retains recorded object property inline comments. Single-line
  properties keep the comment after their comma. When a value expands across
  lines, move its recorded inline comment to a standalone line immediately
  before the property. This keeps the comment stable on reparse without
  changing the decoder. Disabled alignment retains existing comment behavior.
- Determine whether a value spans multiple lines from its rendered output,
  including structured values expanded from one-line input. Exclude that row
  from its surrounding group; still align properties inside the value.
- Each eligible row has at least one space before its delimiter and exactly
  one generated space after it. Pad shorter keys to the longest rendered key
  in the group. A singleton also receives the minimum delimiter spacing.
- Excluded rows retain their existing delimiter spacing.
- Measure assignment keys after shortening them relative to the section.
  Measure object keys after quoting and doubling embedded quotes. Width uses
  PHP byte lengths; Unicode display-column alignment is outside this change.
  Indentation is identical within a group and does not affect relative padding.

Alignment changes only generated whitespace before delimiters. Existing value
rendering continues to own quoting, numeric formatting, and line endings.
Single-line opaque expressions may have their equals sign aligned, but their
value bodies are copied unchanged. Multiline bodies remain unchanged. No
function is evaluated. Never scan finished output for delimiter characters.

## Renderer and validation

Keep grouping and padding in `JinRenderer`. Render values once, retain the
known key boundaries for eligible rows, and insert padding at those boundaries.
Use the same padding operation for assignments and object properties.

Expose internal per-statement rendering records containing the statement and
its canonical text. Full-document rendering consumes these records. Records
retain statement identity rather than using paths as keys, because duplicate
paths can occur in valid source.

`CanonicalLayoutRule` consumes those same records to produce statement-level
diagnostics with the original spans when alignment is enabled. Disabled
alignment retains existing isolated-statement diagnostics, including suggestions
that expand dotted assignments into sections. Statement layout is checked in
source order and canonical ordering is checked separately. An order diagnostic
suggests the complete rendering using the selected ordering policy. Correctly
aligned canonical input must produce no layout diagnostics. Misaligned input
must receive suggestions that include neighboring keys' widths.

An explicitly injected validator retains its own formatting options, matching
the existing `Normalizer` contract. This change does not override it.

## Acceptance and verification

- Confirm immutable configuration, disabled defaults, and existing golden
  output for all profiles.
- Cover assignment groups, section-relative keys, directives, standalone and
  inline comments, blank-line boundaries, and multiline values.
- Cover independent nested objects, objects in lists, quoted keys containing
  delimiters or doubled quotes, and numeric/string formatting options.
- Verify repeated rendering and normalization return identical bytes across
  both ordering policies, all spacing policies, and LF/CRLF output.
- Verify parsed static values remain equal and opaque/multiline bodies remain
  unchanged without evaluating expressions.
- Verify validator acceptance of aligned output, contextual suggestions for
  unaligned input, and independent diagnostics for duplicate paths.
- Exercise the formatting option through normalization, flattening, and diff
  generation, including overlay verification.
- Verify static snapshot comparison reports `unchanged` after alignment.
  Serialized snapshots may differ in recorded source line locations.
- Run the repository checks. Leave unrelated local files untouched.

## Non-goals

No parser changes, inline comment alignment, list alignment, Unicode display
width dependency, configurable padding, maximum-width rules, public layout
framework, automatic file writes, or changes to profile defaults.
