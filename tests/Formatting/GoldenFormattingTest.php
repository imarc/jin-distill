<?php

namespace JinDistill\Tests\Formatting;

use Dotink\Jin\Parser;
use JinDistill\Decoders\JinDecoder;
use JinDistill\Formatting\FormatOptions;
use JinDistill\Formatting\JinRenderer;
use JinDistill\Formatting\LineEnding;
use JinDistill\Formatting\NumericStyle;
use JinDistill\Formatting\OrderingPolicy;
use JinDistill\Formatting\Profiles\DotinkStyle;
use JinDistill\Formatting\Profiles\ImarcStyle;
use JinDistill\Formatting\SectionReferenceStyle;
use JinDistill\Formatting\SpacingPolicy;
use JinDistill\Formatting\StringQuoting;
use PHPUnit\Framework\TestCase;

final class GoldenFormattingTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../fixtures/formatting';

    public function testItRendersEveryConstructCanonically(): void
    {
        self::assertSame(
            file_get_contents(self::FIXTURES . '/all-constructs.expected.jin'),
            $this->render(file_get_contents(self::FIXTURES . '/all-constructs.input.jin')),
        );
    }

    public function testVersionedProfilesKeepIndependentGoldenOutput(): void
    {
        $document = (new JinDecoder())->decode(file_get_contents(self::FIXTURES . '/profile-order.input.jin'));
        $renderer = new JinRenderer();

        self::assertSame(file_get_contents(self::FIXTURES . '/profile-order.v1.jin'), $renderer->render($document, ImarcStyle::v1()));
        self::assertSame(file_get_contents(self::FIXTURES . '/profile-order.v2.jin'), $renderer->render($document, ImarcStyle::v2()));
        self::assertSame(file_get_contents(self::FIXTURES . '/profile-order.v1.jin'), $renderer->render($document, DotinkStyle::v1()));
        self::assertSame(file_get_contents(self::FIXTURES . '/profile-order.v2.jin'), $renderer->render($document, DotinkStyle::v2()));
    }

    public function testItRendersCanonicalSourcesIdempotently(): void
    {
        $once = $this->render(file_get_contents(self::FIXTURES . '/all-constructs.input.jin'));

        self::assertSame($once, $this->render($once));
    }

    public function testItPreservesDotinkSemantics(): void
    {
        $input = $this->withoutInheritance(file_get_contents(self::FIXTURES . '/all-constructs.input.jin'));
        $parser = new Parser([], ['hello' => static fn (string $name): string => 'hi ' . $name], true);

        self::assertSame(
            $parser->parse($input)->all(),
            $parser->parse($this->withoutInheritance($this->render($input)))->all(),
        );
    }

    public function testItAppliesLeadingSpacingPolicy(): void
    {
        $source = "; Heading\n\nname = CPA";

        self::assertSame("; Heading\n\nname = CPA\n", $this->render($source));
        self::assertSame("; Heading\nname = CPA\n", $this->render($source, SpacingPolicy::None));
        self::assertSame("; Heading\n\nname = CPA\n", $this->render($source, SpacingPolicy::One));
    }

    public function testItRendersNestedJsonLeadingCommentsAndSpacing(): void
    {
        self::assertSame(<<<'JIN'
[form]
	fields = {
		"person": {

			; Personal Information

			"avatar": false,
		},
	}
JIN
 . "\n", $this->render(<<<'JIN'
[form]
    fields = {
        "person": {

            ; Personal Information

            "avatar": false,
        },
    }
JIN));
    }

    public function testItPreservesNumericLexemesAtEveryStaticDepth(): void
    {
        $source = "count = 0xD\nfields = {\"number\": 1.50, \"list\": [10, 2.00]}";
        $options = (new FormatOptions())->withNumericStyle(NumericStyle::Preserve);
        $preserved = (new JinRenderer())->render((new JinDecoder())->decode($source), $options);

        self::assertStringContainsString("count = 0xD\n", $preserved);
        self::assertStringContainsString('"number": 1.50,', $preserved);
        self::assertStringContainsString("\t\t10,\n\t\t2.00,", $preserved);
        self::assertSame($preserved, (new JinRenderer())->render((new JinDecoder())->decode($preserved), $options));
        self::assertSame((new Parser())->parse($source)->all(), (new Parser())->parse($preserved)->all());
    }

    public function testItKeepsCommentsAndSpacingBeforeListElements(): void
    {
        $source = "items = [\n\t; First\n\n\t1,\n\t2,\n]";
        $rendered = (new JinRenderer())->render((new JinDecoder())->decode($source));

        self::assertStringContainsString("[\n\t; First\n\n\t1,\n\t2,\n]", $rendered);
        self::assertSame($rendered, (new JinRenderer())->render((new JinDecoder())->decode($rendered)));
    }

    public function testItFormatsStringsAndLineEndingsWithoutChangingValues(): void
    {
        $source = "name = CPA\n[form]\nlabel = say hello";
        $options = (new FormatOptions())->withStringQuoting(StringQuoting::Always)->withLineEnding(LineEnding::CrLf);
        $rendered = (new JinRenderer())->render((new JinDecoder())->decode($source), $options);

        self::assertSame("name = \"CPA\"\r\n[form]\r\n\tlabel = \"say hello\"\r\n", $rendered);
        self::assertSame($rendered, (new JinRenderer())->render((new JinDecoder())->decode($rendered), $options));
        self::assertSame((new Parser())->parse($source)->all(), (new Parser())->parse($rendered)->all());
    }

    public function testItCanPreserveRelativeSectionReferences(): void
    {
        $source = "[form]\n[&.fields]\nname = CPA";
        $document = (new JinDecoder())->decode($source);
        $renderer = new JinRenderer();

        self::assertStringContainsString("[form.fields]\n", $renderer->render($document));
        self::assertStringContainsString("[&.fields]\n", $renderer->render($document, (new FormatOptions())->withSectionReferences(SectionReferenceStyle::Preserve)));
    }

    public function testItUsesExplicitReferencesWhenCanonicalGroupingChangesSectionOrder(): void
    {
        $source = "[form]\n[&.fields]\nname = CPA\n[other]\nx = 1\n[form]\n[&.details]\ny = 2";
        $options = (new FormatOptions())->withSectionReferences(SectionReferenceStyle::Preserve);
        $rendered = (new JinRenderer())->render((new JinDecoder())->decode($source), $options);

        self::assertStringContainsString('[form.details]', $rendered);
        self::assertStringNotContainsString('[&.details]', $rendered);
    }

    public function testItGroupsRepeatedSectionsOnlyInCanonicalOrder(): void
    {
        $source = "[beta]\nb = 2\n[alpha]\na = 1\n[beta]\nc = 3";
        $document = (new JinDecoder())->decode($source);
        $renderer = new JinRenderer();

        self::assertSame("[beta]\n\tb = 2\n[alpha]\n\ta = 1\n[beta]\n\tc = 3\n", $renderer->render($document, (new FormatOptions())->withOrdering(OrderingPolicy::SourceOrder)));
        self::assertSame("[beta]\n\tb = 2\n\tc = 3\n[alpha]\n\ta = 1\n", $renderer->render($document));
    }

    public function testCanonicalGroupingKeepsCommentsFromRepeatedSectionHeadings(): void
    {
        $source = "[beta]\nb = 2\n[alpha]\na = 1\n; Second beta\n[beta]\nc = 3";
        $rendered = (new JinRenderer())->render((new JinDecoder())->decode($source));

        self::assertStringContainsString("[beta]\n\tb = 2\n\t; Second beta\n\tc = 3", $rendered);
        self::assertSame($rendered, (new JinRenderer())->render((new JinDecoder())->decode($rendered)));
    }

    private function render(string $contents, ?SpacingPolicy $spacing = null): string
    {
        $options = $spacing === null ? null : (new FormatOptions())->withSpacingPolicy($spacing);

        return (new JinRenderer())->render((new JinDecoder())->decode($contents), $options);
    }

    private function withoutInheritance(string $contents): string
    {
        return preg_replace('/^--(extends|without).*\n(\t?".*",\n\]\n)?/m', '', $contents);
    }
}
