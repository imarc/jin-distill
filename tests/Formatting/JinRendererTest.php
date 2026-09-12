<?php

namespace JinDistill\Tests\Formatting;

use JinDistill\Decoders\JinDecoder;
use JinDistill\Formatting\JinRenderer;
use JinDistill\Formatting\Profiles\DotinkStyle;
use JinDistill\Formatting\Profiles\ImarcStyle;
use PHPUnit\Framework\TestCase;

final class JinRendererTest extends TestCase
{
    public function testItRendersOrderedSyntaxWithCanonicalStaticValues(): void
    {
        $document = (new JinDecoder())->decode(<<<'JIN'
            ; Root title
            title=CPA

            [form]
            name="CPA Society" ; public label
            limit=0x10
            fields={"first":true,"tags":["one","two",],}
            JIN);

        self::assertSame(
            "; Root title\ntitle = CPA\n\n[form]\n"
            . "\tname = CPA Society ; public label\n"
            . "\tlimit = 16\n"
            . "\tfields = {\n"
            . "\t\t\"first\": true,\n"
            . "\t\t\"tags\": [\n"
            . "\t\t\t\"one\",\n"
            . "\t\t\t\"two\",\n"
            . "\t\t],\n"
            . "\t}\n",
            (new JinRenderer())->render($document),
        );
    }

    public function testItPreservesOpaqueAndMultilineValueBodies(): void
    {
        $document = (new JinDecoder())->decode(<<<'JIN'
            expression=run( value, { "keep":  true } )
            template=first line
              second line
            JIN);

        self::assertSame(<<<'JIN'
            expression = run( value, { "keep":  true } )
            template = first line
              second line
            JIN . "\n", (new JinRenderer())->render($document));
    }

    public function testItRendersExtendsReferencesForEachProfile(): void
    {
        $document = (new JinDecoder())->decode('--extends=base.jin');
        $renderer = new JinRenderer();

        self::assertSame("--extends = file(base.jin)\n", $renderer->render($document, ImarcStyle::v1()));
        self::assertSame("--extends = base.jin\n", $renderer->render($document, DotinkStyle::v1()));
    }

    public function testItEscapesQuotesTheWayJinReadsThem(): void
    {
        $document = (new JinDecoder())->decode("quoted = \"literal \"\"quote\"\"\"\nlist = [\n\t\"q\"\"q\",\n]");

        $rendered = (new JinRenderer())->render($document);

        self::assertStringContainsString('quoted = "literal ""quote"""', $rendered);
        self::assertStringContainsString('"q""q",', $rendered);
        self::assertSame(
            (new \Dotink\Jin\Parser())->parse($document->contents())->all(),
            (new \Dotink\Jin\Parser())->parse($rendered)->all(),
        );
    }
}
