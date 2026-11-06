<?php

namespace JinDistill\Tests\Formatting;

use JinDistill\Decoders\JinDecoder;
use JinDistill\Formatting\FormatOptions;
use JinDistill\Formatting\JinRenderer;
use JinDistill\Formatting\LineEnding;
use JinDistill\Formatting\OrderingPolicy;
use JinDistill\Formatting\Profiles\DotinkStyle;
use JinDistill\Formatting\Profiles\ImarcStyle;
use JinDistill\Formatting\SpacingPolicy;
use PHPUnit\Framework\TestCase;

final class JinRendererTest extends TestCase
{
    public function testItAlignsAssignmentsAndEachNestedObjectIndependently(): void
    {
        $source = <<<'JIN'
class = Payment
email = emails.dues
template = @emails/payments/firm-paid-dues.html
options = {"class": "Payment", "email": "emails.dues", "nested": {"x": 1, "long": 2}, "x": 3, "tail": 4}
items = [{"a": 1, "long": 2}, {"b": 3, "longer": 4}]
[form.fields]
x = 1
long = 2
JIN;
        $rendered = (new JinRenderer())->render((new JinDecoder())->decode($source), ImarcStyle::v2()->withAssignmentAlignment(true));

        self::assertSame(
            "class    = Payment\nemail    = emails.dues\ntemplate = @emails/payments/firm-paid-dues.html\n"
            . "options = {\n\t\"class\" : \"Payment\",\n\t\"email\" : \"emails.dues\",\n"
            . "\t\"nested\": {\n\t\t\"x\"    : 1,\n\t\t\"long\" : 2,\n\t},\n"
            . "\t\"x\"    : 3,\n\t\"tail\" : 4,\n}\n"
            . "items = [\n\t{\n\t\t\"a\"    : 1,\n\t\t\"long\" : 2,\n\t},\n"
            . "\t{\n\t\t\"b\"      : 3,\n\t\t\"longer\" : 4,\n\t},\n]\n"
            . "[form.fields]\n\tx    = 1\n\tlong = 2\n",
            $rendered,
        );
        self::assertSame((new \Dotink\Jin\Parser())->parse($source)->all(), (new \Dotink\Jin\Parser())->parse($rendered)->all());
    }

    public function testItBreaksGroupsAtCommentsDirectivesBlanksAndMultilineValues(): void
    {
        $source = "--extends=base.jin\n--custom=value\nx=1\nlong=2\ncommented=3 ; label\na=4\nbb=5\n\nc=6\nddd=7\n; Break\ne=8\nff=9\nbody=first line\n  <keep = : this body>\ng=10\nhhh=11\nexpression=run( keep = : this )";
        $rendered = (new JinRenderer())->render((new JinDecoder())->decode($source), ImarcStyle::v2()->withAssignmentAlignment(true));

        self::assertSame(
            "--extends = file(base.jin)\n--custom = value\nx    = 1\nlong = 2\ncommented = 3 ; label\n"
            . "a  = 4\nbb = 5\n\nc   = 6\nddd = 7\n; Break\ne  = 8\nff = 9\n"
            . "body = first line\n  <keep = : this body>\ng          = 10\nhhh        = 11\nexpression = run( keep = : this )\n",
            $rendered,
        );
    }

    public function testAlignmentIsIdempotentAcrossOrderingSpacingAndLineEndingPolicies(): void
    {
        $source = "x=1\n\n\nlong=2\nobject={\"a\":1,\n\n\"long\":2,\n; Break\n\"q\":3,\"tail\":4,\"nested\":{\"x\":5,\"long\":6}}\nexpression=run( keep = : this )\n[beta]\nx=1\nlong=2\n[alpha]\ny=3\n[beta]\nz=4";
        $decoder = new JinDecoder();
        $renderer = new JinRenderer();

        foreach (OrderingPolicy::cases() as $ordering) {
            foreach (SpacingPolicy::cases() as $spacing) {
                foreach (LineEnding::cases() as $ending) {
                    $options = (new FormatOptions())->withAssignmentAlignment(true)
                        ->withOrdering($ordering)->withSpacingPolicy($spacing)->withLineEnding($ending);
                    $once = $renderer->render($decoder->decode($source), $options);

                    self::assertSame($once, $renderer->render($decoder->decode($once), $options), "$ordering->name/$spacing->name/$ending->name");
                    self::assertStringContainsString(
                        $spacing === SpacingPolicy::None ? 'x    = 1' : 'x = 1',
                        $once,
                    );
                    self::assertStringContainsString('run( keep = : this )', $once);
                }
            }
        }
    }

    public function testItMeasuresQuotedObjectKeysWithoutScanningTheirDelimiters(): void
    {
        $source = 'options = {"a:b": "keep = : this", "q""q": 2, "é": 3}';
        $decoder = new JinDecoder();
        $renderer = new JinRenderer();
        $options = ImarcStyle::v2()->withAssignmentAlignment(true);
        $rendered = $renderer->render($decoder->decode($source), $options);

        self::assertSame("options = {\n\t\"a:b\"  : \"keep = : this\",\n\t\"q\"\"q\" : 2,\n\t\"é\"   : 3,\n}\n", $rendered);
        self::assertSame($rendered, $renderer->render($decoder->decode($rendered), $options));
        self::assertSame((new \Dotink\Jin\Parser())->parse($source)->all(), (new \Dotink\Jin\Parser())->parse($rendered)->all());
    }

    public function testObjectCommentsAndBlankLinesRemainStableGroupBoundaries(): void
    {
        $source = <<<'JIN'
options = {
    "a": 1,
    "long": 2,
    "commented": 3, ; Inline
    "b": 4,
    "longer": 5,

    "c": 6,
    ; Break
    "d": 7,
    "tail": 8,
}
JIN;
        $decoder = new JinDecoder();
        $renderer = new JinRenderer();
        $options = ImarcStyle::v2()->withAssignmentAlignment(true);
        $rendered = $renderer->render($decoder->decode($source), $options);

        self::assertSame(
            "options = {\n\t\"a\"    : 1,\n\t\"long\" : 2,\n\t\"commented\": 3, ; Inline\n"
            . "\t\"b\"      : 4,\n\t\"longer\" : 5,\n\n\t\"c\" : 6,\n\t; Break\n"
            . "\t\"d\"    : 7,\n\t\"tail\" : 8,\n}\n",
            $rendered,
        );
        self::assertSame($rendered, $renderer->render($decoder->decode($rendered), $options));
        self::assertSame((new \Dotink\Jin\Parser())->parse($source)->all(), (new \Dotink\Jin\Parser())->parse($rendered)->all());
    }

    public function testRecordedInlineCommentsBecomeLeadingCommentsWhenPropertiesExpand(): void
    {
        $decoder = new JinDecoder();
        $renderer = new JinRenderer();

        foreach (['nested' => '{"a": 1, "long": 2}', 'items' => '[1, 2]'] as $key => $value) {
            $source = "options = {\n    \"$key\": $value, ; Keep\n}";
            foreach (LineEnding::cases() as $ending) {
                $options = ImarcStyle::v2()->withAssignmentAlignment(true)->withLineEnding($ending);
                $rendered = $renderer->render($decoder->decode($source), $options);

                self::assertStringContainsString("\t; Keep" . $ending->value . "\t\"$key\": " . $value[0], $rendered);
                self::assertSame($rendered, $renderer->render($decoder->decode($rendered), $options));
                self::assertSame((new \Dotink\Jin\Parser())->parse($source)->all(), (new \Dotink\Jin\Parser())->parse($rendered)->all());
            }
        }
    }

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
