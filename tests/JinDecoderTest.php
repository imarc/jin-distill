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

function test_jin_decoder_preserves_values_when_section_is_reopened(): void
{
    $decoder = new JinDecoder();
    $document = $decoder->decode(<<<'JIN'
[form.fields]
    firstName = true

[form.fields]
    lastName = true
JIN);

    assertSameValue(true, $document->data['form']['fields']['firstName'], 'First section value remains after section is reopened.');
    assertSameValue(true, $document->data['form']['fields']['lastName'], 'Second section value is added.');
}

function test_jin_decoder_parses_json_like_strings_with_literal_backslashes(): void
{
    $decoder = new JinDecoder();
    $document = $decoder->decode(<<<'JIN'
[routing]
    middleware = {
        "handler": "App\Middleware\ResponseHandler",
    }
JIN);

    assertSameValue('App\Middleware\ResponseHandler', $document->data['routing']['middleware']['handler'], 'Literal backslashes are valid in Jin JSON-like strings.');
}

function test_jin_decoder_rejects_directives_after_sections(): void
{
    $decoder = new JinDecoder();

    assertThrows(
        fn() => $decoder->decode("[form]\n--without = name\nname = CPA\n"),
        JinDistill\Exceptions\InvalidStructureException::class,
        'File-level directives after a section should throw.'
    );
}

function test_jin_decoder_stores_json_land_key_comment_metadata(): void
{
    $decoder = new JinDecoder();
    $document = $decoder->decode(<<<'JIN'
[form]
    fields = {
        "person": {
            ; First name label
            "firstName": true, ; required
        },
    }
JIN);

    assertSameValue(['First name label'], $document->metadata['form.fields.person.firstName']['leadingComments'], 'JSON-land key leading comment is stored.');
    assertSameValue('required', $document->metadata['form.fields.person.firstName']['inlineComment'], 'JSON-land key inline comment is stored.');
}
