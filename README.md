# JinDistill

Analyze, normalize, flatten, and diff [Dotink/Jin](https://github.com/dotink/jin)
configuration. Every workflow above is **static**: JinDistill parses Jin source
into a syntax model and never executes `run()`, `env()`, or any other Jin
function unless you explicitly ask it to.

- PHP 8.1 – 8.4
- `dotink/jin` ^4.9
- MIT licensed

```bash
composer require imarc/jin-distill
```

## Safe workflows

```php
use JinDistill\JinDistiller;

$distiller = new JinDistiller();
```

### Analyze

Builds the inheritance graph and the provenance of every assignment.

```php
$analysis = $distiller->analyzeFile('config/forms/child.jin');

$analysis->mode();                  // AnalysisMode::SourceOnly
$analysis->sourceGraph()->edges();  // child -> parent inheritance edges
$analysis->provenance();            // which file won each path
```

### Normalize

Rewrites one file into canonical layout. Parents are analyzed but never
rewritten, and nothing is written to disk — you get the content back.

```php
$result = $distiller->normalizeFile('config/forms/child.jin');

file_put_contents('config/forms/child.jin', $result->content());
$result->diagnostics();  // style findings with suggested fixes
```

### Flatten

Composes a file and its ancestors into one standalone document.

```php
echo $distiller->flattenFile('config/forms/child.jin')->content();
```

### Diff

Generates the minimum inheritance document whose resolved configuration equals
the target. The output path is never written; you decide what to do with the
content.

```php
use JinDistill\Diff\DiffOptions;

$diff = $distiller->diffFiles(
    parentPath: 'config/forms/base.jin',
    targetPath: 'config/forms/full.jin',
    outputPath: 'config/forms/generated.jin',
    options: (new DiffOptions())->withVerification(true),
);

echo $diff->content();
$diff->removals();              // paths planned for --without
$diff->verification();          // static proof the overlay equals the target
```

See `examples/diff/` for the golden inputs and generated output.

## Evaluation (executes PHP)

> [!WARNING]
> `evaluateFile()`, `verifySemantics()`, and `verifyFileSemantics()` hand the source to Dotink's parser.
> Jin's `run()` executes arbitrary PHP and `env()` reads the environment. Only
> evaluate configuration you trust, and read `docs/security.md` first.

```php
use JinDistill\Evaluation\EvaluationOptions;

$evaluated = $distiller->evaluateFile('config/app.jin', new EvaluationOptions(
    context: [],
    functions: ['file' => static fn (string $path): string => __DIR__ . '/' . $path],
));

$evaluated->resolvedData();
```

Applications may reuse their runtime Jin configuration through a parser factory:

```php
use JinDistill\Evaluation\JinEvaluator;

$distiller = $distiller->withEvaluator(new JinEvaluator(
    fn (): Dotink\Jin\Parser => $runtimeParserFactory(),
));

$flattened = $distiller->flattenFile('config/app.jin')->content();
$verification = $distiller->verifyFileSemantics('config/app.jin', $flattened);
```

The factory must return a fresh parser on every call. Caller-provided evaluators
are used only by explicit evaluation workflows; static workflows remain
non-executing. Per-call `EvaluationOptions` override the configured evaluator.

## Reports

```php
use JinDistill\Reporting\ReportSerializer;

echo (new ReportSerializer())->toJson($diff);
```

Evaluated values are redacted unless you pass
`new ReportOptions(includeValues: true)`. The schema is documented in
`docs/report-schema-v1.md`.

## Documentation

| Document | Contents |
| --- | --- |
| `docs/api.md` | every public facade method and result accessor |
| `docs/formatting.md` | style profiles, diagnostics, and what stays raw |
| `docs/compatibility.md` | PHP and Dotink support policy |
| `docs/security.md` | what executes, what does not, and how reports redact |
| `docs/report-schema-v1.md` | the V1 report schema |
| `docs/private-corpus.md` | how a private fixture corpus plugs into CI |

## Development

```bash
composer install
composer check   # tests, PHPStan (max), style, manifest validation
```
