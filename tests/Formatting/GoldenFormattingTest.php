<?php

namespace JinDistill\Tests\Formatting;

use Dotink\Jin\Parser;
use JinDistill\Decoders\JinDecoder;
use JinDistill\Formatting\FormatOptions;
use JinDistill\Formatting\JinRenderer;
use JinDistill\Formatting\SpacingPolicy;
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
