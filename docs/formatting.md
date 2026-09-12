# Formatting

Formatting is controlled by an immutable `Formatting\FormatOptions` object.
Rendering walks the ordered syntax model, so comments, order, and raw
expressions survive; only layout is decided by the profile.

## Profiles

| Profile | Indentation | Line ending | Extends style |
| --- | --- | --- | --- |
| `Profiles\ImarcStyle::v1()` (default) | tab | LF | `file(path)` |
| `Profiles\DotinkStyle::v1()` | tab | LF | bare relative path |

Other V1 defaults, shared by both: decimal numbers, minimal-safe string
quoting, explicit section paths, assignment-level diff granularity, and
metadata-sensitive diffs.

Profile versions are frozen public API. A change of default ships as `v2()`.

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
- integers normalized to decimal (`0xD`, `0b1101`, and `015` all render `13`)
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

Noncanonical but valid source is accepted. `Validation\Validator` reports it:
each statement is compared against its canonical rendering and any difference
becomes a `jin.style.canonical-layout` diagnostic whose `suggestion()` is the
canonical text. Indentation, quoting, trailing commas, and spacing all surface
through that one comparison; CRLF files add `jin.style.line-ending`, and
relative section references add `jin.style.section-reference`.

Rules are objects (`Validation\Rules\*`) implementing `Validation\Rule`; pass
your own list to the `Validator` constructor to narrow or extend the policy.

## Idempotence

Rendering canonical output again returns identical bytes. The golden fixtures
in `tests/fixtures/formatting/` assert this, and also assert that Dotink
resolves the input and the canonical output to the same data.
