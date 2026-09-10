<?php

namespace JinDistill\Tests;

use JinDistill\Decoders\DecoderInterface;
use JinDistill\Decoders\JinDecoder;
use JinDistill\Exceptions\InvalidStructureException;
use JinDistill\JinDocument;
use PHPUnit\Framework\TestCase;

final class JinDecoderTest extends TestCase
{
    public function testItImplementsDecoderInterface(): void
    {
        self::assertInstanceOf(DecoderInterface::class, new JinDecoder());
    }

    public function testItParsesRootValuesSectionsAndJsonLikeValues(): void
    {
        $document = (new JinDecoder())->decode(<<<'JIN'
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

        self::assertInstanceOf(JinDocument::class, $document);
        self::assertNull($document->data['home']);
        self::assertTrue($document->data['enabled']);
        self::assertSame(13, $document->data['count']);
        self::assertSame('CPA', $document->data['form']['name']);
        self::assertTrue($document->data['form']['fields']['person']['firstName']);
    }

    public function testItParsesExtendsAndWithoutDirectives(): void
    {
        $document = (new JinDecoder())->decode(<<<'JIN'
--extends = file(base.jin)
--without = [
    "form.name",
    "form.fields.person.avatar",
]

[form]

    name = CPA
JIN);

        self::assertSame('base.jin', $document->directives['extends']);
        self::assertSame(['form.name', 'form.fields.person.avatar'], $document->directives['without']);
        self::assertSame('CPA', $document->data['form']['name']);
    }

    public function testItParsesSingleWithoutDirective(): void
    {
        $document = (new JinDecoder())->decode("--without = form.name\nname = CPA\n");

        self::assertSame(['form.name'], $document->directives['without']);
    }

    public function testItStoresLeadingAndInlineCommentMetadata(): void
    {
        $document = (new JinDecoder())->decodeFile(__DIR__ . '/fixtures/comments.jin');

        self::assertSame(['Document overview', 'Base form configuration'], $document->metadata['--extends']['leadingComments']);
        self::assertSame('parent file', $document->metadata['--extends']['inlineComment']);
        self::assertSame(['Form section'], $document->metadata['form']['leadingComments']);
        self::assertSame(['Public display name'], $document->metadata['form.name']['leadingComments']);
        self::assertSame('visible label', $document->metadata['form.name']['inlineComment']);
        self::assertSame(['Required person fields'], $document->metadata['form.fields']['leadingComments']);
    }

    public function testItPreservesValuesWhenSectionIsReopened(): void
    {
        $document = (new JinDecoder())->decode(<<<'JIN'
[form.fields]
    firstName = true

[form.fields]
    lastName = true
JIN);

        self::assertTrue($document->data['form']['fields']['firstName']);
        self::assertTrue($document->data['form']['fields']['lastName']);
    }

    public function testItParsesJsonLikeStringsWithLiteralBackslashes(): void
    {
        $document = (new JinDecoder())->decode(<<<'JIN'
[routing]
    middleware = {
        "handler": "App\Middleware\ResponseHandler",
    }
JIN);

        self::assertSame('App\Middleware\ResponseHandler', $document->data['routing']['middleware']['handler']);
    }

    public function testItRejectsDirectivesAfterSections(): void
    {
        $this->expectException(InvalidStructureException::class);

        (new JinDecoder())->decode("[form]\n--without = name\nname = CPA\n");
    }

    public function testItStoresJsonLandKeyCommentMetadata(): void
    {
        $document = (new JinDecoder())->decode(<<<'JIN'
[form]
    fields = {
        "person": {
            ; First name label
            "firstName": true, ; required
        },
    }
JIN);

        self::assertSame(['First name label'], $document->metadata['form.fields.person.firstName']['leadingComments']);
        self::assertSame('required', $document->metadata['form.fields.person.firstName']['inlineComment']);
    }
}
