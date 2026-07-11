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
