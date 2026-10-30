# Compatibility

## Supported runtimes

| Component | Supported |
| --- | --- |
| PHP | 8.1, 8.2, 8.3, 8.4 |
| `dotink/jin` | ^4.9, baseline 4.9.0 |

CI runs the full suite on every listed PHP version against the 4.9.0 baseline,
plus one job on PHP 8.4 that updates `dotink/jin` to the newest release inside
`^4.9` to catch drift early.

## Jin language coverage

JinDistill parses and re-renders every construct in the Dotink 4.9 grammar:

- root assignments, `[section]` groups, and relative `[&.section]` references
- JSON-like objects and lists, including trailing commas
- quoted strings using Jin's doubled-quote escape (`"say ""hi"""`)
- hexadecimal, binary, and octal integers, normalized to decimal
- comments, both leading and inline
- multiline values
- function calls: `env()`, `run()`, `file()`, `def()`, `inc()`, `map()`, and
  custom registered functions
- the `--extends` and `--without` inheritance directives

Function bodies and multiline values are preserved byte-for-byte. JinDistill
normalizes the layout around them, never their contents.

## Semver promises

Within a major version:

- public facade signatures and result accessors keep their meaning
- diagnostic rule IDs keep their meaning; new IDs may be added in minor releases
- report schema keys keep their meaning; new optional keys may be added
- style profile versions (`ImarcStyle::v1()`) are frozen — a changed default
  ships as `v2()`

## Pre-1.0 note

Everything before 1.0 is replaced, not deprecated. The legacy
`Formats\JinFormat` array encoder is retained as a deliberate adapter for
callers that hold plain PHP arrays rather than parsed Jin source; new code
should use `Formatting\JinRenderer` through the facade.
