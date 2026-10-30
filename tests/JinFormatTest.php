<?php

namespace JinDistill\Tests;

use JinDistill\Formats\FormatInterface;
use JinDistill\Formats\JinFormat;
use JinDistill\JinDocument;
use PHPUnit\Framework\TestCase;

final class JinFormatTest extends TestCase
{
    public function testItImplementsFormatInterface(): void
    {
        self::assertInstanceOf(FormatInterface::class, new JinFormat());
    }

    public function testItEncodesLegacyExtendsAndCanOmitIt(): void
    {
        $format = new JinFormat(boundary: 1, tabs: "\t", strict: true);
        $data = [
            '--extends' => 'local/forms/application/standard.jin',
            'form' => ['name' => 'CPA'],
        ];

        self::assertSame(
            "--extends = file(local/forms/application/standard.jin)\n\n[form]\n\n\tname = CPA\n",
            $format->encode($data)
        );
        self::assertSame("[form]\n\n\tname = CPA\n", $format->encode($data, false));
    }

    public function testItEncodesJsonLandWithTrailingCommas(): void
    {
        $format = new JinFormat(boundary: 1, tabs: '  ', strict: true);
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

        self::assertSame(
            "[form]\n\n  name = CPA\n\n  fields = {\n    \"person\": {\n      \"firstName\": true,\n      \"lastName\": true,\n    },\n  }\n",
            $format->encode($data)
        );
    }

    public function testItEncodesDocumentDirectivesAndData(): void
    {
        $document = new JinDocument(
            data: ['form' => ['name' => 'CPA']],
            directives: ['extends' => 'base.jin', 'without' => ['form.fields.person.avatar']],
        );

        self::assertSame(
            "--extends = file(base.jin)\n--without = [\n\t\"form.fields.person.avatar\",\n]\n\n[form]\n\n\tname = CPA\n",
            (new JinFormat())->encodeDocument($document)
        );
    }

    public function testItEmitsLeadingAndInlineComments(): void
    {
        $document = new JinDocument(
            data: ['form' => ['name' => 'CPA']],
            metadata: [
                'form' => ['leadingComments' => ['Form section'], 'inlineComment' => null],
                'form.name' => ['leadingComments' => ['Public display name'], 'inlineComment' => 'visible label'],
            ]
        );

        self::assertSame(
            "; Form section\n[form]\n\n\t; Public display name\n\tname = CPA ; visible label\n",
            (new JinFormat())->encodeDocument($document, comments: true)
        );
    }

    public function testItEmitsJsonLandComments(): void
    {
        $document = new JinDocument(
            data: ['form' => ['fields' => ['person' => ['firstName' => true]]]],
            metadata: [
                'form.fields.person.firstName' => [
                    'leadingComments' => ['First name label'],
                    'inlineComment' => 'required',
                ],
            ]
        );

        self::assertSame(
            "[form]\n\n\tfields = {\n\t\t\"person\": {\n\t\t\t; First name label\n\t\t\t\"firstName\": true, ; required\n\t\t},\n\t}\n",
            (new JinFormat())->encodeDocument($document, comments: true)
        );
    }

    public function testItEscapesQuotesTheWayJinReadsThem(): void
    {
        $encoded = (new JinFormat())->encode(['label' => 'say ""hi""', 'list' => ['a"b']]);

        self::assertStringContainsString('label = "say """"hi"""""', $encoded);
        self::assertStringContainsString('"a""b",', $encoded);
        self::assertSame(
            ['label' => 'say ""hi""', 'list' => ['a"b']],
            (new \Dotink\Jin\Parser())->parse($encoded)->all(),
        );
    }
}
