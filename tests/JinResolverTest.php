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
