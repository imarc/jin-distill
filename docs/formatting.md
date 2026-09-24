# Formatting

Formatting is controlled by an immutable `Formatting\FormatOptions` object.
Rendering walks the ordered syntax model. Raw function bodies and multiline
values survive. Options select layout and which inherited leading comments to
carry into flattened or diff output.

## Profiles

`new FormatOptions()` and `Profiles\ImarcStyle::v2()` use the 2.0 defaults:
tabs, LF, nearest-definition comments, canonical sections, Hiraeth `file()`
extends paths, decimal numbers, minimal-safe strings, explicit section paths,
and preserved blank lines. `Profiles\DotinkStyle::v2()` differs only in its
bare relative extends paths.

`ImarcStyle::v1()` and `DotinkStyle::v1()` retain their 1.x output, including
source statement order and historical JSON comment behavior. Select them
explicitly when output must remain byte-stable.

```php
use JinDistill\Formatting\LineEnding;
use JinDistill\Formatting\OrderingPolicy;
use JinDistill\Formatting\Profiles\ImarcStyle;
use JinDistill\Formatting\StringQuoting;

$format = ImarcStyle::v2()
    ->withIndentation('  ')
    ->withLineEnding(LineEnding::CrLf)
    ->withOrdering(OrderingPolicy::SourceOrder)
    ->withStringQuoting(StringQuoting::Always);

$result = $distiller->normalizeFile('config/app.jin', $format);
```

## Options

- `withIndentation(string)`: non-empty string, applied once per rendered depth.
- `withLineEnding(LineEnding::Lf|CrLf)`: ending for generated lines. Raw
  multiline values keep their source bytes.
- `withLeadingComments(LeadingCommentPolicy::WinnerOnly|NearestDefinition)`:
  select winning definition's leading comments, or nearest ancestor comments
  when winner has none. Applies to assignments, sections, and JSON members.
  Inline comments always come from winner.
- `withOrdering(OrderingPolicy::SourceOrder|CanonicalSections)`: keep source
  statement order, or group directives, root assignments, and sections in
  first-owner order. Comments and spacing move with their statements.
- `withExtendsPathStyle(InheritancePathStyle::BareRelative|HiraethFile)`:
  emit bare relative paths or `file(path)` calls. Existing
  `ExtendsPathStyle::BareRelative` and `::HiraethFile` constants hold those enum
  cases for compatibility; PHP cannot declare an enum named `ExtendsPathStyle`.
- `withNumericStyle(NumericStyle::Decimal|Preserve)`: canonical decimal, or
  original equivalent number token for assignments, objects, and lists.
  Generated values without source tokens use decimal.
- `withStringQuoting(StringQuoting::MinimalSafe|Always)`: quote ambiguous
  strings only, or quote every string value. Object keys stay quoted.
- `withSectionReferences(SectionReferenceStyle::Explicit|Preserve)`: absolute
  section paths, or original lexemes where safe. Generated sections and
  sections moved by canonical grouping use absolute paths.
- `withSpacingPolicy(SpacingPolicy::Preserve|None|One)`: keep, remove, or cap
  recorded leading blank lines at one.

## Leading spacing

Blank lines belong to the node that follows them, including assignments and
members in JSON-like values. Leading comments remain attached when blank lines
separate them from that node.

`FormatOptions::withSpacingPolicy()` controls emitted leading blank lines:

- `SpacingPolicy::Preserve` (default) keeps their source count.
- `SpacingPolicy::None` removes them.
- `SpacingPolicy::One` emits one where source contained one or more.

The policy applies to assignments, sections, and JSON-like members.

## Canonical layout

- one assignment per line, `key = value`
- section groups written as explicit absolute paths: `[form.fields]`
- members inside a section indented one level
- JSON-like objects and lists broken across lines with a trailing comma after
  every element
- strings quoted only when needed — when empty, padded, numeric-looking,
  boolean-looking, or containing `;`, quotes, braces, brackets, or newlines
- quotes escaped by doubling (`"say ""hi"""`), which is what Dotink reads.
  Backslash escaping is a parse error in Jin, so it is never emitted
- integers normalized to decimal by default (`0xD`, `0b1101`, and `015` all render `13`)
- one blank line before each section in generated diff documents

## What stays raw

Function bodies and multiline values are copied byte-for-byte:

```jin
runValue = run(md5('value'))
template = def(value) {
    {"value": $value}
}
```

JinDistill never reformats what it did not parse into values, and never
executes them to find out what they mean.

## Diagnostics, not rejection

Noncanonical but valid source is accepted. `Validation\Validator` reports it
relative to the selected `FormatOptions`:
each statement is compared against its canonical rendering and any difference
becomes a `jin.style.canonical-layout` diagnostic whose `suggestion()` is the
canonical text. Indentation, quoting, trailing commas, and spacing all surface
through that one comparison. Line-ending and section-reference diagnostics
follow the selected style rather than treating every non-default choice as
invalid.

Rules are objects (`Validation\Rules\*`) implementing `Validation\Rule`; pass
your own list to the `Validator` constructor to narrow or extend the policy.

## Idempotence

Rendering canonical output again returns identical bytes. The golden fixtures
in `tests/fixtures/formatting/` assert this, and also assert that Dotink
resolves the input and the canonical output to the same data.
