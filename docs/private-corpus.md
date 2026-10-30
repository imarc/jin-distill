# Private Corpus Contract

Real Fuse and CPA configuration cannot live in this repository: it carries
client names, credentials, and proprietary structure. The public CI matrix runs
only the fixtures in `tests/fixtures` and `examples/`.

A private runner may additionally exercise a real corpus through one input:

| Name | Type | Meaning |
| --- | --- | --- |
| `JIN_DISTILL_PRIVATE_CORPUS` | absolute directory path | root of an untracked fixture tree |

## Contract

1. The private runner checks out this repository unchanged and mounts its own
   fixture directory plus its own PHPUnit suite named `private`.
2. It exports `JIN_DISTILL_PRIVATE_CORPUS` as an absolute path to that
   directory, then runs `composer test -- --testsuite private`.
3. The private suite asserts the same 1.0 guarantees the public suite does:
   normalization is idempotent, flattening and diffing preserve Dotink
   semantics, and no analysis executes a Jin expression.
4. Failures are reported by path and rule ID only. Evaluated values stay
   redacted, exactly as `ReportOptions` does by default.

## What never enters this repository

- Credentials, tokens, connection strings, or `.env` material.
- Client-identifying fixtures or their file names.
- The private PHPUnit suite definition itself.

Nothing in the public workflow reads `JIN_DISTILL_PRIVATE_CORPUS`; absence of
the variable is the normal case and must never fail a build.
