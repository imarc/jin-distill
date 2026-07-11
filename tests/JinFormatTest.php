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
