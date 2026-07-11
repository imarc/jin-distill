<?php

require __DIR__ . '/../vendor/autoload.php';

$files = [
    __DIR__ . '/JinDocumentTest.php',
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
