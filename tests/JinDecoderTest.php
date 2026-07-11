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
