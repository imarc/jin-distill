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
