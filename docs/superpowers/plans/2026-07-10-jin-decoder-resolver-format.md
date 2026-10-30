# Jin Decoder Resolver Format Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Split Jin parsing, inheritance resolution, normalization, and formatting into focused classes while preserving existing `FormatInterface` encoder compatibility.

**Architecture:** Add `JinDecoder` to parse `.jin` text into a metadata-aware `JinDocument`, `JinResolver` to apply `--extends` and `--without`, `JinFormat` to encode arrays/documents back to normalized `.jin`, and `JinDistiller` to expose workflow methods such as `normalizeFile()` and `flattenFile()`. Keep `CsvFormat`, `Encoder`, and `FormatInterface` conceptually unchanged.

**Tech Stack:** PHP 8.1+ style code already used by the package, Composer PSR-4 autoloading, no new runtime dependencies, simple PHP assertion test runner.

---

## File Structure

Create these files:

- `src/JinDistill/Decoders/DecoderInterface.php`: minimal decoder contract for classes that turn strings into PHP objects/documents.
- `src/JinDistill/Decoders/JinDecoder.php`: parser for Jin text into `JinDocument`.
- `src/JinDistill/JinDocument.php`: immutable-ish value object for data, directives, metadata, and source path.
- `src/JinDistill/JinResolver.php`: resolves `--extends`, applies inherited-data `--without`, and deep-merges child data.
- `src/JinDistill/JinDistiller.php`: high-level workflow facade for `normalize()`, `normalizeFile()`, `flattenFile()`, and `resolveFile()`.
- `src/JinDistill/Support/Arr.php`: dot-path set/get/delete and deep-merge helpers.
- `tests/run.php`: small test runner that fails with non-zero exit code.
- `tests/JinFormatTest.php`: tests for array/document encoding, boundary behavior, trailing commas, and legacy compatibility.
- `tests/JinDecoderTest.php`: tests for parsing sections, assignments, directives, JSON-like values, and comment metadata.
- `tests/JinResolverTest.php`: tests for file inheritance, `--without`, deep merge, relative paths, and circular detection.
- `tests/JinDistillerTest.php`: tests for normalize and flatten workflows.
- `tests/fixtures/base.jin`: parent fixture for resolver tests.
- `tests/fixtures/child.jin`: child fixture extending `base.jin`.
- `tests/fixtures/comments.jin`: fixture for leading and inline comments.

Modify these files:

- `src/JinDistill/Formats/JinFormat.php`: rebuild as encoder/formatter only; keep `implements FormatInterface`.
- `README.md`: add short usage examples for decoding, normalizing, and flattening.

Leave these files unchanged unless tests expose an integration issue:

- `src/JinDistill/Formats/FormatInterface.php`: keep `public function encode(array $data): string;`.
- `src/JinDistill/Formats/CsvFormat.php`: remains an array-to-CSV encoder.
- `src/JinDistill/Encoder.php`: remains the output coordinator for `FormatInterface` encoders.

## Design Constraints

- `JinFormat` must continue to implement `FormatInterface`.
- `JinFormat::encode(array $data, bool $extensions = true): string` is allowed because the extra parameter is optional and remains interface-compatible.
- Remove constructor-level `$extensions` from `JinFormat`.
- Keep constructor parameters as `boundary`, `tabs`, and `strict`.
- Parse directives into `JinDocument::$directives`, not only magic data keys.
- Preserve legacy array input where `--extends` and `--without` appear as array keys.
- Store only `leadingComments` and `inlineComment`; do not model trailing comments.
- A blank line breaks leading-comment association.
- JSON-land output must always include trailing commas where valid.
- `--without` dot paths remove values from inherited parent data before child values are merged.
- Do not implement `run()`, `env()`, `def()`, `inc()`, or `map()` in this phase.
- Do not copy `Dotink\Jin\Parser` internals or rely on `parse_ini_string()` as the parser architecture.

## Task 1: Add A Simple Test Harness

**Files:**

- Create: `tests/run.php`

- [ ] **Step 1: Create the failing test harness**

Create `tests/run.php` with this content:

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

$files = [
    __DIR__ . '/JinFormatTest.php',
    __DIR__ . '/JinDecoderTest.php',
    __DIR__ . '/JinResolverTest.php',
    __DIR__ . '/JinDistillerTest.php',
];

$failures = 0;

function assertSameValue($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            "%s\nExpected: %s\nActual: %s",
            $message,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

function assertTrueValue(bool $actual, string $message): void
{
    if ($actual !== true) {
        throw new RuntimeException($message);
    }
}

function assertThrows(callable $callback, string $exceptionClass, string $message): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        if ($exception instanceof $exceptionClass) {
            return;
        }

        throw new RuntimeException(sprintf(
            "%s\nExpected exception: %s\nActual exception: %s",
            $message,
            $exceptionClass,
            get_class($exception)
        ));
    }

    throw new RuntimeException(sprintf(
        "%s\nExpected exception: %s\nActual exception: none",
        $message,
        $exceptionClass
    ));
}

foreach ($files as $file) {
    if (file_exists($file)) {
        require $file;
    }
}

foreach (get_defined_functions()['user'] as $function) {
    if (!str_starts_with($function, 'test_')) {
        continue;
    }

    try {
        $function();
        fwrite(STDOUT, ".");
    } catch (Throwable $exception) {
        $failures++;
        fwrite(STDOUT, "F\n\n{$function}\n{$exception->getMessage()}\n");
    }
}

fwrite(STDOUT, "\n");

if ($failures > 0) {
    fwrite(STDERR, "{$failures} test failure(s).\n");
    exit(1);
}

fwrite(STDOUT, "All tests passed.\n");
```

- [ ] **Step 2: Run test harness to verify baseline**

Run: `php tests/run.php`

Expected: `All tests passed.` because no test files exist yet.

- [ ] **Step 3: Commit**

```bash
git add tests/run.php
git commit -m "test: add php test harness"
```

## Task 2: Add JinDocument And Array Path Helpers

**Files:**

- Create: `src/JinDistill/JinDocument.php`
- Create: `src/JinDistill/Support/Arr.php`
- Create: `tests/JinDocumentTest.php`
- Modify: `tests/run.php`

- [ ] **Step 1: Register document tests in runner**

Modify the `$files` array in `tests/run.php` to include the document test before format/decoder tests:

```php
$files = [
    __DIR__ . '/JinDocumentTest.php',
    __DIR__ . '/JinFormatTest.php',
    __DIR__ . '/JinDecoderTest.php',
    __DIR__ . '/JinResolverTest.php',
    __DIR__ . '/JinDistillerTest.php',
];
```

- [ ] **Step 2: Write failing tests**

Create `tests/JinDocumentTest.php` with this content:

```php
<?php

use JinDistill\JinDocument;
use JinDistill\Support\Arr;

function test_jin_document_stores_data_directives_metadata_and_path(): void
{
    $document = new JinDocument(
        data: ['form' => ['name' => 'CPA']],
        directives: ['extends' => 'base.jin', 'without' => ['form.name']],
        metadata: ['form.name' => ['leadingComments' => ['Name'], 'inlineComment' => 'inline']],
        path: '/tmp/forms/cpa.jin'
    );

    assertSameValue(['form' => ['name' => 'CPA']], $document->data, 'Document stores data.');
    assertSameValue('base.jin', $document->directives['extends'], 'Document stores extends directive.');
    assertSameValue(['form.name'], $document->directives['without'], 'Document stores without directive.');
    assertSameValue('/tmp/forms/cpa.jin', $document->path, 'Document stores path.');
}

function test_arr_deletes_dot_path_and_deep_merges_assoc_arrays(): void
{
    $data = [
        'form' => [
            'name' => 'Default',
            'fields' => [
                'person' => [
                    'firstName' => true,
                    'avatar' => false,
                ],
            ],
        ],
    ];

    Arr::delete($data, 'form.fields.person.avatar');

    assertSameValue(false, isset($data['form']['fields']['person']['avatar']), 'Dot path is deleted.');

    $merged = Arr::mergeDistinct($data, [
        'form' => [
            'name' => 'CPA',
            'fields' => [
                'person' => [
                    'lastName' => true,
                ],
            ],
        ],
    ]);

    assertSameValue('CPA', $merged['form']['name'], 'Child scalar replaces parent scalar.');
    assertSameValue(true, $merged['form']['fields']['person']['firstName'], 'Parent nested value remains.');
    assertSameValue(true, $merged['form']['fields']['person']['lastName'], 'Child nested value is added.');
}
```

- [ ] **Step 3: Run tests to verify failure**

Run: `php tests/run.php`

Expected: FAIL with class not found for `JinDistill\JinDocument` or `JinDistill\Support\Arr`.

- [ ] **Step 4: Implement JinDocument**

Create `src/JinDistill/JinDocument.php` with this content:

```php
<?php

namespace JinDistill;

class JinDocument
{
    public function __construct(
        public array $data = [],
        public array $directives = [],
        public array $metadata = [],
        public ?string $path = null,
    ) {
    }

    public function withData(array $data): self
    {
        return new self($data, $this->directives, $this->metadata, $this->path);
    }

    public function withoutDirectives(): self
    {
        return new self($this->data, [], $this->metadata, $this->path);
    }
}
```

- [ ] **Step 5: Implement Arr helpers**

Create `src/JinDistill/Support/Arr.php` with this content:

```php
<?php

namespace JinDistill\Support;

class Arr
{
    public static function set(array &$data, string $path, mixed $value): void
    {
        $segments = explode('.', $path);
        $target = &$data;

        foreach ($segments as $segment) {
            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                $target[$segment] = [];
            }

            $target = &$target[$segment];
        }

        $target = $value;
    }

    public static function delete(array &$data, string $path): void
    {
        $segments = explode('.', $path);
        $last = array_pop($segments);
        $target = &$data;

        foreach ($segments as $segment) {
            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                return;
            }

            $target = &$target[$segment];
        }

        unset($target[$last]);
    }

    public static function mergeDistinct(array $parent, array $child): array
    {
        foreach ($child as $key => $value) {
            if (
                array_key_exists($key, $parent) &&
                is_array($parent[$key]) &&
                is_array($value) &&
                !array_is_list($parent[$key]) &&
                !array_is_list($value)
            ) {
                $parent[$key] = self::mergeDistinct($parent[$key], $value);
                continue;
            }

            $parent[$key] = $value;
        }

        return $parent;
    }
}
```

- [ ] **Step 6: Run tests to verify pass**

Run: `php tests/run.php`

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add tests/run.php tests/JinDocumentTest.php src/JinDistill/JinDocument.php src/JinDistill/Support/Arr.php
git commit -m "feat: add jin document model"
```

## Task 3: Rebuild JinFormat As Encoder-Only Formatter

**Files:**

- Modify: `src/JinDistill/Formats/JinFormat.php`
- Create: `tests/JinFormatTest.php`

- [ ] **Step 1: Write failing formatter tests**

Create `tests/JinFormatTest.php` with this content:

```php
<?php

use JinDistill\Formats\FormatInterface;
use JinDistill\Formats\JinFormat;
use JinDistill\JinDocument;

function test_jin_format_still_implements_format_interface(): void
{
    $format = new JinFormat();

    assertTrueValue($format instanceof FormatInterface, 'JinFormat implements FormatInterface.');
}

function test_jin_format_encodes_legacy_extends_by_default_and_can_omit_it(): void
{
    $format = new JinFormat(boundary: 1, tabs: "\t", strict: true);

    $data = [
        '--extends' => 'local/forms/application/standard.jin',
        'form' => ['name' => 'CPA'],
    ];

    assertSameValue(
        "--extends = file(local/forms/application/standard.jin)\n\n[form]\n\n\tname = CPA\n",
        $format->encode($data),
        'Legacy extends is emitted by default.'
    );

    assertSameValue(
        "[form]\n\n\tname = CPA\n",
        $format->encode($data, false),
        'Legacy extends can be omitted.'
    );
}

function test_jin_format_encodes_json_land_with_trailing_commas(): void
{
    $format = new JinFormat(boundary: 1, tabs: "  ", strict: true);

    $data = [
        'form' => [
            'name' => 'CPA',
            'fields' => [
                'person' => [
                    'firstName' => true,
                    'lastName' => true,
                ],
            ],
        ],
    ];

    assertSameValue(
        "[form]\n\n  name = CPA\n\n  fields = {\n    \"person\": {\n      \"firstName\": true,\n      \"lastName\": true,\n    },\n  }\n",
        $format->encode($data),
        'JSON-land objects include trailing commas and configured indentation.'
    );
}

function test_jin_format_encodes_document_directives_and_data(): void
{
    $format = new JinFormat();
    $document = new JinDocument(
        data: ['form' => ['name' => 'CPA']],
        directives: ['extends' => 'base.jin', 'without' => ['form.fields.person.avatar']],
    );

    assertSameValue(
        "--extends = file(base.jin)\n--without = [\n\t\"form.fields.person.avatar\",\n]\n\n[form]\n\n\tname = CPA\n",
        $format->encodeDocument($document),
        'Document directives are emitted before data.'
    );
}
```

- [ ] **Step 2: Run tests to verify failure**

Run: `php tests/run.php`

Expected: FAIL because current constructor still has `$extensions`, output differs, and `encodeDocument()` does not exist.

- [ ] **Step 3: Replace JinFormat implementation**

Replace `src/JinDistill/Formats/JinFormat.php` with an encoder-only implementation that has these public signatures:

```php
public function __construct(
    protected int $boundary = 1,
    protected string $tabs = "\t",
    protected bool $strict = true,
) {
}

public function encode(array $data, bool $extensions = true): string
public function encodeDocument(JinDocument $document, bool $extensions = true, bool $comments = false): string
```

Implementation requirements:

- Reset output state at the start of every encode.
- Extract legacy `--extends` and `--without` keys from array input before encoding data.
- When `$extensions` is true, output `--extends = file(path)` and `--without` before sections/data.
- Do not emit directives when `$extensions` is false.
- Emit top-level associative arrays as `[section]` blocks.
- Emit primitive top-level keys as `key = value`.
- When `$depth >= $boundary`, switch to JSON-land and output objects/arrays inline as multiline JSON-like blocks.
- Always use trailing commas for JSON-land object properties and list values.
- Quote strings in JSON-land with `json_encode($value, JSON_UNESCAPED_SLASHES)`.
- In INI-land, leave simple strings unquoted, but quote strings containing semicolon, newline, leading/trailing whitespace, `{`, `}`, `[`, `]`, or scalar-looking values `true`, `false`, `null`.
- Throw `InvalidStructureException` in strict mode when a list array appears before JSON-land and cannot be represented as INI structure.

- [ ] **Step 4: Run tests to verify pass**

Run: `php tests/run.php`

Expected: PASS for document and format tests.

- [ ] **Step 5: Commit**

```bash
git add src/JinDistill/Formats/JinFormat.php tests/JinFormatTest.php
git commit -m "feat: rebuild jin formatter"
```

## Task 4: Add JinDecoder For Basic Documents And Directives

**Files:**

- Create: `src/JinDistill/Decoders/DecoderInterface.php`
- Create: `src/JinDistill/Decoders/JinDecoder.php`
- Create: `tests/JinDecoderTest.php`

- [ ] **Step 1: Write failing decoder tests**

Create `tests/JinDecoderTest.php` with this content:

```php
<?php

use JinDistill\Decoders\DecoderInterface;
use JinDistill\Decoders\JinDecoder;
use JinDistill\JinDocument;

function test_jin_decoder_implements_decoder_interface(): void
{
    $decoder = new JinDecoder();

    assertTrueValue($decoder instanceof DecoderInterface, 'JinDecoder implements DecoderInterface.');
}

function test_jin_decoder_parses_root_values_sections_and_json_like_values(): void
{
    $decoder = new JinDecoder();
    $document = $decoder->decode(<<<'JIN'
home = null
enabled = true
count = 13
name = CPA

[form]

    name = CPA
    fields = {
        "person": {
            "firstName": true,
            "lastName": true,
        },
    }
JIN);

    assertTrueValue($document instanceof JinDocument, 'Decoder returns JinDocument.');
    assertSameValue(null, $document->data['home'], 'Null scalar parsed.');
    assertSameValue(true, $document->data['enabled'], 'Boolean scalar parsed.');
    assertSameValue(13, $document->data['count'], 'Integer scalar parsed.');
    assertSameValue('CPA', $document->data['form']['name'], 'Section value parsed.');
    assertSameValue(true, $document->data['form']['fields']['person']['firstName'], 'Nested JSON-like object parsed.');
}

function test_jin_decoder_parses_extends_and_without_directives(): void
{
    $decoder = new JinDecoder();
    $document = $decoder->decode(<<<'JIN'
--extends = file(base.jin)
--without = [
    "form.name",
    "form.fields.person.avatar",
]

[form]

    name = CPA
JIN);

    assertSameValue('base.jin', $document->directives['extends'], 'Extends directive parsed.');
    assertSameValue(['form.name', 'form.fields.person.avatar'], $document->directives['without'], 'Without directive parsed.');
    assertSameValue('CPA', $document->data['form']['name'], 'Data remains separate from directives.');
}

function test_jin_decoder_parses_single_without_directive(): void
{
    $decoder = new JinDecoder();
    $document = $decoder->decode("--without = form.name\nname = CPA\n");

    assertSameValue(['form.name'], $document->directives['without'], 'Single without path becomes array.');
}
```

- [ ] **Step 2: Run tests to verify failure**

Run: `php tests/run.php`

Expected: FAIL because decoder classes do not exist.

- [ ] **Step 3: Implement DecoderInterface**

Create `src/JinDistill/Decoders/DecoderInterface.php` with this content:

```php
<?php

namespace JinDistill\Decoders;

interface DecoderInterface
{
    public function decode(string $contents, ?string $path = null): mixed;

    public function decodeFile(string $path): mixed;
}
```

- [ ] **Step 4: Implement JinDecoder scanner**

Create `src/JinDistill/Decoders/JinDecoder.php` with methods that satisfy these signatures:

```php
public function __construct(protected bool $strict = true)
public function decode(string $contents, ?string $path = null): JinDocument
public function decodeFile(string $path): JinDocument
```

Implementation requirements:

- Normalize `\r\n` and `\r` to `\n`.
- Read lines sequentially.
- Track current section path as a string or `null`.
- Ignore blank lines for parsing.
- Parse section lines matching `^\s*\[([^\]]+)\]`.
- Parse assignment lines matching `^\s*([A-Za-z0-9_.:\-]+|--[A-Za-z0-9_.:\-]+)\s*=\s*(.*)$`.
- For values beginning with `{` or `[`, collect following lines until braces/brackets are balanced outside quoted strings.
- Strip comments outside quoted strings before value parsing.
- Parse `file(path)` for `--extends` as `path`.
- Parse `--without` as either one string path or an array of string paths.
- Convert booleans and null case-insensitively.
- Convert integer and float numeric values.
- Parse quoted strings, unescaping doubled quotes `""` to `"`.
- Parse JSON-like arrays/objects by removing trailing commas before `]` and `}`, preserving literal backslashes enough for PHP class names.
- Use `Arr::set()` to insert section values into nested data.
- Throw `InvalidStructureException` on malformed values when strict.

- [ ] **Step 5: Run tests to verify pass**

Run: `php tests/run.php`

Expected: PASS through decoder tests.

- [ ] **Step 6: Commit**

```bash
git add src/JinDistill/Decoders/DecoderInterface.php src/JinDistill/Decoders/JinDecoder.php tests/JinDecoderTest.php
git commit -m "feat: add jin decoder"
```

## Task 5: Preserve Leading And Inline Comment Metadata

**Files:**

- Modify: `src/JinDistill/Decoders/JinDecoder.php`
- Modify: `tests/JinDecoderTest.php`
- Create: `tests/fixtures/comments.jin`

- [ ] **Step 1: Add comment fixture**

Create `tests/fixtures/comments.jin` with this content:

```jin
; Document overview
; Base form configuration
--extends = file(base.jin) ; parent file

; Form section
[form]

    ; Public display name
    name = CPA ; visible label

    ; Required person fields
    fields = {
        "person": {
            "firstName": true,
        },
    }
```

- [ ] **Step 2: Add failing metadata test**

Append this test to `tests/JinDecoderTest.php`:

```php
function test_jin_decoder_stores_leading_and_inline_comment_metadata(): void
{
    $decoder = new JinDecoder();
    $document = $decoder->decodeFile(__DIR__ . '/fixtures/comments.jin');

    assertSameValue(
        ['Document overview', 'Base form configuration'],
        $document->metadata['--extends']['leadingComments'],
        'Directive leading comments are stored.'
    );
    assertSameValue('parent file', $document->metadata['--extends']['inlineComment'], 'Directive inline comment is stored.');
    assertSameValue(['Form section'], $document->metadata['form']['leadingComments'], 'Section leading comment is stored.');
    assertSameValue(['Public display name'], $document->metadata['form.name']['leadingComments'], 'Key leading comment is stored.');
    assertSameValue('visible label', $document->metadata['form.name']['inlineComment'], 'Key inline comment is stored.');
    assertSameValue(['Required person fields'], $document->metadata['form.fields']['leadingComments'], 'JSON-like assignment leading comment is stored on assignment path.');
}
```

- [ ] **Step 3: Run tests to verify failure**

Run: `php tests/run.php`

Expected: FAIL because metadata is not yet populated.

- [ ] **Step 4: Implement comment capture**

Update `JinDecoder` with this behavior:

- Maintain `$pendingComments = []` while scanning lines.
- A line whose first non-whitespace character is `;` adds the trimmed comment text to `$pendingComments`.
- A blank line clears `$pendingComments`.
- When a section, directive, or assignment is parsed, attach pending comments to that path as `leadingComments`, then clear the pending comments.
- Split inline comments from a value only when `;` occurs outside quoted strings and outside balanced `{}` or `[]` values.
- Store inline comments as `inlineComment`.
- Store `line` and `source` metadata for each parsed section, directive, or assignment.

The metadata entry shape must be:

```php
[
    'leadingComments' => ['Comment line'],
    'inlineComment' => 'inline text or null',
    'line' => 12,
    'source' => '/absolute/or/provided/path.jin',
]
```

- [ ] **Step 5: Run tests to verify pass**

Run: `php tests/run.php`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/JinDistill/Decoders/JinDecoder.php tests/JinDecoderTest.php tests/fixtures/comments.jin
git commit -m "feat: capture jin comment metadata"
```

## Task 6: Add JinResolver For Extends And Without

**Files:**

- Create: `src/JinDistill/JinResolver.php`
- Create: `tests/JinResolverTest.php`
- Create: `tests/fixtures/base.jin`
- Create: `tests/fixtures/child.jin`
- Create: `tests/fixtures/circular-a.jin`
- Create: `tests/fixtures/circular-b.jin`

- [ ] **Step 1: Create resolver fixtures**

Create `tests/fixtures/base.jin` with this content:

```jin
[form]

    name = Default
    fields = {
        "person": {
            "avatar": false,
            "firstName": true,
            "middleName": false,
            "lastName": true,
        },
        "agreements": {
            "terms": false,
        },
    }
```

Create `tests/fixtures/child.jin` with this content:

```jin
--extends = file(base.jin)
--without = [
    "form.fields.person.avatar",
    "form.fields.person.middleName",
]

[form]

    name = CPA
    fields = {
        "person": {
            "birthDate": true,
        },
    }
```

Create `tests/fixtures/circular-a.jin` with this content:

```jin
--extends = file(circular-b.jin)
name = A
```

Create `tests/fixtures/circular-b.jin` with this content:

```jin
--extends = file(circular-a.jin)
name = B
```

- [ ] **Step 2: Write failing resolver tests**

Create `tests/JinResolverTest.php` with this content:

```php
<?php

use JinDistill\Decoders\JinDecoder;
use JinDistill\Exceptions\InvalidStructureException;
use JinDistill\JinResolver;

function test_jin_resolver_resolves_extends_without_and_child_overrides(): void
{
    $resolver = new JinResolver(new JinDecoder());
    $document = $resolver->resolveFile(__DIR__ . '/fixtures/child.jin');

    assertSameValue('CPA', $document->data['form']['name'], 'Child scalar overrides parent.');
    assertSameValue(true, $document->data['form']['fields']['person']['firstName'], 'Inherited value remains.');
    assertSameValue(true, $document->data['form']['fields']['person']['lastName'], 'Inherited sibling remains.');
    assertSameValue(false, isset($document->data['form']['fields']['person']['avatar']), 'Without removes inherited avatar.');
    assertSameValue(false, isset($document->data['form']['fields']['person']['middleName']), 'Without removes inherited middleName.');
    assertSameValue(true, $document->data['form']['fields']['person']['birthDate'], 'Child nested value is merged.');
    assertSameValue([], $document->directives, 'Resolved document omits inheritance directives by default.');
}

function test_jin_resolver_detects_circular_extends(): void
{
    $resolver = new JinResolver(new JinDecoder());

    assertThrows(
        fn() => $resolver->resolveFile(__DIR__ . '/fixtures/circular-a.jin'),
        InvalidStructureException::class,
        'Circular extends should throw.'
    );
}
```

- [ ] **Step 3: Run tests to verify failure**

Run: `php tests/run.php`

Expected: FAIL because `JinResolver` does not exist.

- [ ] **Step 4: Implement JinResolver**

Create `src/JinDistill/JinResolver.php` with this public surface:

```php
public function __construct(protected JinDecoder $decoder)
public function resolveFile(string $path, bool $directives = false): JinDocument
public function resolveDocument(JinDocument $document, bool $directives = false, array $seen = []): JinDocument
```

Implementation requirements:

- Convert file paths to real paths when possible for circular detection.
- Resolve `--extends` relative to `dirname($document->path)`.
- Throw `InvalidStructureException` when an extended file cannot be resolved or read.
- Recursively resolve parent before merging the child.
- Apply every child `without` path to the parent data using `Arr::delete()` before merge.
- Merge with `Arr::mergeDistinct($parentData, $childData)`.
- Return a `JinDocument` with merged data, child metadata merged over parent metadata, and empty directives unless `$directives` is true.
- If `$directives` is true, preserve the child document directives for traceability.

- [ ] **Step 5: Run tests to verify pass**

Run: `php tests/run.php`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/JinDistill/JinResolver.php tests/JinResolverTest.php tests/fixtures/base.jin tests/fixtures/child.jin tests/fixtures/circular-a.jin tests/fixtures/circular-b.jin
git commit -m "feat: resolve jin inheritance"
```

## Task 7: Add JinDistiller Workflow Facade

**Files:**

- Create: `src/JinDistill/JinDistiller.php`
- Create: `tests/JinDistillerTest.php`

- [ ] **Step 1: Write failing distiller tests**

Create `tests/JinDistillerTest.php` with this content:

```php
<?php

use JinDistill\JinDistiller;

function test_jin_distiller_normalizes_file_without_resolving_extends(): void
{
    $distiller = new JinDistiller();
    $output = $distiller->normalizeFile(__DIR__ . '/fixtures/child.jin');

    assertTrueValue(str_contains($output, '--extends = file(base.jin)'), 'Normalize preserves extends by default.');
    assertTrueValue(str_contains($output, '--without = ['), 'Normalize preserves without by default.');
    assertTrueValue(str_contains($output, '"birthDate": true,'), 'Normalize formats child values.');
}

function test_jin_distiller_flattens_file_to_standalone_jin(): void
{
    $distiller = new JinDistiller();
    $output = $distiller->flattenFile(__DIR__ . '/fixtures/child.jin');

    assertTrueValue(!str_contains($output, '--extends'), 'Flatten omits extends.');
    assertTrueValue(!str_contains($output, '--without'), 'Flatten omits without.');
    assertTrueValue(str_contains($output, 'name = CPA'), 'Flatten includes child override.');
    assertTrueValue(str_contains($output, '"firstName": true,'), 'Flatten includes inherited value.');
    assertTrueValue(!str_contains($output, '"avatar"'), 'Flatten excludes without path.');
    assertTrueValue(str_contains($output, '"birthDate": true,'), 'Flatten includes child merged value.');
}
```

- [ ] **Step 2: Run tests to verify failure**

Run: `php tests/run.php`

Expected: FAIL because `JinDistiller` does not exist.

- [ ] **Step 3: Implement JinDistiller**

Create `src/JinDistill/JinDistiller.php` with this public surface:

```php
public function __construct(
    ?JinDecoder $decoder = null,
    ?JinResolver $resolver = null,
    ?JinFormat $format = null,
)
public function decode(string $contents, ?string $path = null): JinDocument
public function decodeFile(string $path): JinDocument
public function resolveFile(string $path): JinDocument
public function normalize(string $contents, ?string $path = null, bool $extensions = true): string
public function normalizeFile(string $path, bool $extensions = true): string
public function flattenFile(string $path): string
```

Implementation requirements:

- Default constructor creates `JinDecoder`, `JinResolver`, and `JinFormat`.
- `normalize()` decodes one document and formats it; it does not resolve inheritance.
- `normalizeFile()` reads one document and formats it; it does not resolve inheritance.
- `flattenFile()` resolves inheritance and formats the resolved document with extensions disabled.

- [ ] **Step 4: Run tests to verify pass**

Run: `php tests/run.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/JinDistill/JinDistiller.php tests/JinDistillerTest.php
git commit -m "feat: add jin distiller workflows"
```

## Task 8: Add Comment Re-Emission Option To JinFormat

**Files:**

- Modify: `src/JinDistill/Formats/JinFormat.php`
- Modify: `tests/JinFormatTest.php`

- [ ] **Step 1: Add failing comment output test**

Append this test to `tests/JinFormatTest.php`:

```php
function test_jin_format_can_emit_leading_and_inline_comments_from_document_metadata(): void
{
    $format = new JinFormat();
    $document = new JinDocument(
        data: ['form' => ['name' => 'CPA']],
        metadata: [
            'form' => ['leadingComments' => ['Form section'], 'inlineComment' => null],
            'form.name' => ['leadingComments' => ['Public display name'], 'inlineComment' => 'visible label'],
        ]
    );

    assertSameValue(
        "; Form section\n[form]\n\n\t; Public display name\n\tname = CPA ; visible label\n",
        $format->encodeDocument($document, comments: true),
        'Formatter can re-emit stored comments.'
    );
}
```

- [ ] **Step 2: Run tests to verify failure**

Run: `php tests/run.php`

Expected: FAIL because comments are ignored by formatter.

- [ ] **Step 3: Implement optional comment output**

Update `JinFormat::encodeDocument()` and internal write methods so that when `$comments` is true:

- Leading comments for a path are emitted immediately before the directive, section, or assignment they belong to.
- Inline comment is emitted as ` ; comment` after INI-land primitive assignments.
- Inline comments are not emitted inside JSON-land values in this phase.
- Comments are omitted by default.

- [ ] **Step 4: Run tests to verify pass**

Run: `php tests/run.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/JinDistill/Formats/JinFormat.php tests/JinFormatTest.php
git commit -m "feat: emit jin comments from metadata"
```

## Task 9: Document Public Usage

**Files:**

- Modify: `README.md`

- [ ] **Step 1: Update README**

Replace `README.md` with this content:

```markdown
# jin-distill

Utilities for decoding, resolving, normalizing, and exporting Jin configuration.

## Encode PHP Data As Jin

```php
use JinDistill\Formats\JinFormat;

$format = new JinFormat(boundary: 1, tabs: "\t", strict: true);

echo $format->encode([
    '--extends' => 'local/forms/application/standard.jin',
    'form' => [
        'name' => 'CPA',
        'fields' => [
            'person' => [
                'firstName' => true,
            ],
        ],
    ],
]);
```

## Decode Jin

```php
use JinDistill\Decoders\JinDecoder;

$decoder = new JinDecoder();
$document = $decoder->decodeFile(__DIR__ . '/form.jin');

$data = $document->data;
$directives = $document->directives;
$metadata = $document->metadata;
```

## Normalize One File

```php
use JinDistill\JinDistiller;

$distiller = new JinDistiller();

echo $distiller->normalizeFile(__DIR__ . '/form.jin');
```

Normalization parses one file and writes it back using the formatter rules. It does not resolve `--extends`.

## Flatten An Extending File

```php
use JinDistill\JinDistiller;

$distiller = new JinDistiller();

echo $distiller->flattenFile(__DIR__ . '/local-form.jin');
```

Flattening resolves `--extends`, applies child `--without` paths to inherited data, merges child values over parent values, and emits standalone Jin without inheritance directives.

## Comment Metadata

The decoder stores leading and inline comments in `JinDocument::$metadata` so they can be re-emitted later.

```jin
; Public display name
name = CPA ; visible label
```

Becomes metadata for the path `name`:

```php
[
    'leadingComments' => ['Public display name'],
    'inlineComment' => 'visible label',
]
```
```

- [ ] **Step 2: Run tests**

Run: `php tests/run.php`

Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add README.md
git commit -m "docs: document jin distill workflows"
```

## Task 10: Final Verification

**Files:**

- No file changes expected.

- [ ] **Step 1: Regenerate Composer autoload if needed**

Run: `composer dump-autoload`

Expected: Composer completes successfully and updates autoload metadata if needed.

- [ ] **Step 2: Run full test suite**

Run: `php tests/run.php`

Expected: `All tests passed.`

- [ ] **Step 3: Check git status**

Run: `git status --short`

Expected: no uncommitted changes except files intentionally left uncommitted by the user.

- [ ] **Step 4: Review recent commits**

Run: `git log --oneline -10`

Expected: recent commits show the task commits in order.

## Future Work

- Add section reference support for `[&.child]` and `[&&.child]`.
- Preserve and re-emit comments inside JSON-land values.
- Add source spans to metadata for better diagnostics.
- Add escaped dot-path support for literal dotted keys in `--without`, such as `nesting.dotKey.butt\.test`.
- Add optional `env()` and custom function evaluation behind explicit configuration.
- Treat `run()` as opt-in only because it executes PHP code from configuration.
- Add `def()`, `inc()`, and `map()` support after the parser has token/node coverage for function bodies.

## Self-Review

- Spec coverage: The plan covers parser/decoder split, resolver inheritance, formatter compatibility, trailing commas, constructor changes, `--extends`, `--without`, comment metadata, normalize, and flatten.
- Placeholder scan: No task uses incomplete placeholders; each task has exact files, test content, expected command output, and implementation requirements.
- Type consistency: `JinDocument`, `JinDecoder`, `JinResolver`, `JinDistiller`, `JinFormat::encode()`, and `JinFormat::encodeDocument()` signatures are consistent across tasks.
