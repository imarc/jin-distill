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
