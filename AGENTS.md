# Package changes and releases

This Composer package is used by other projects. Treat published versions as
immutable, and preserve compatibility deliberately.

## Compatibility and semantic versioning

- Develop changes on a feature branch based on current `main`. Keep unrelated
  local changes out of commits and pull requests.
- Before editing, trace the affected behavior and review existing conventions.
  Preserve defaults and public contracts unless the change explicitly requires
  a breaking release.
- Review public classes, methods, named arguments, return types, exceptions,
  formatting output, diagnostics, inheritance and diff semantics, serialized
  report/lock schemas, and supported PHP/dependency versions for compatibility.
  An internal implementation change can still break one of these contracts.
- Use a **patch** release for backward-compatible corrections to documented
  behavior. Use a **minor** release for backward-compatible additions, including
  opt-in features and deprecations. Use a **major** release for removals,
  incompatible signatures or behavior, changed defaults, incompatible persisted
  schemas, or increased minimum PHP/dependency requirements.
- Classify the complete release since the previous published version. The
  highest-impact change determines the version; commit labels and file counts
  do not. Explain uncertain classifications before choosing a version.
- Add user-visible changes to `CHANGELOG.md` under `Unreleased`. Document
  compatibility implications and provide migration steps for breaking changes.
  Keep released entries unchanged except for clearly identified documentation
  corrections.
- Cover changed behavior with regression tests, including preserved defaults
  and affected integrations. Follow the user's requirement to ask before
  writing test files. Keep static workflows free of expression evaluation.

## Review and CI gates

- Before requesting merge, review the full diff against current `main`, match
  it to the spec, and resolve every actionable finding. Review package contents,
  dependency changes, public API compatibility, and the changelog as well as code.
- Run the checks defined in `composer.json` and inspect every job required by
  `.github/workflows/ci.yml`, including the supported PHP matrix and baseline
  and latest-compatible Dotink coverage. A local run is not proof that remote
  CI passed.
- Tie approval and passing CI to the exact reviewed commit. New code, dependency,
  or release-preparation changes require renewed relevant checks and review.
  Report skipped, failing, pending, or unavailable checks explicitly.
- Prepare a concrete diff and validation summary before requesting permission
  to stage or commit when session instructions require it. Feature-branch or
  pull-request approval does not authorize a release tag or publication.

## Release gate

1. Complete review and obtain passing CI for the final pull-request commit.
   Confirm the intended semantic version and release contents with the user.
2. Merge only after merge authorization. Verify the resulting `main` commit
   contains the reviewed changes and all required CI jobs pass for that commit.
3. Finalize the changelog with the approved version and actual release date.
   Include release-preparation changes in review and CI before tagging. Composer
   derives this package's version from Git tags; do not add a `version` field
   to `composer.json`.
4. Before creating or pushing a tag, obtain explicit release approval naming
   the exact version and commit. Verify the version has never been published
   by checking local tags, remote tags, and Packagist versions. Stop on any
   conflict or unresolved review/CI issue.
5. Create an annotated `vX.Y.Z` tag at the approved, tested `main` commit. Push
   only that tag, then verify its remote target and Packagist metadata. Release
   automation and GitHub releases require the same publication approval.

Never move, replace, delete and recreate, or force-push a published version tag.
Treat a pushed version tag as published immediately, even before Packagist
shows it. Fix a bad release with a new reviewed version and explain the affected
versions; coordinate deprecation or abandonment separately. Never report a
release as ready while review, CI, or user approval remains outstanding.
