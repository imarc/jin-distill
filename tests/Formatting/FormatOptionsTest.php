<?php

namespace JinDistill\Tests\Formatting;

use JinDistill\Formatting\ExtendsPathStyle;
use JinDistill\Formatting\FormatOptions;
use JinDistill\Formatting\LeadingCommentPolicy;
use JinDistill\Formatting\LineEnding;
use JinDistill\Formatting\NumericStyle;
use JinDistill\Formatting\OrderingPolicy;
use JinDistill\Formatting\Profiles\DotinkStyle;
use JinDistill\Formatting\Profiles\ImarcStyle;
use JinDistill\Formatting\SectionReferenceStyle;
use JinDistill\Formatting\SpacingPolicy;
use JinDistill\Formatting\StringQuoting;
use PHPUnit\Framework\TestCase;

final class FormatOptionsTest extends TestCase
{
    public function testImarcV1HasVersionedSafeDefaults(): void
    {
        $options = ImarcStyle::v1();

        self::assertSame("\t", $options->indentation());
        self::assertSame(LineEnding::Lf, $options->lineEnding());
        self::assertSame(NumericStyle::Decimal, $options->numericStyle());
        self::assertSame(StringQuoting::MinimalSafe, $options->stringQuoting());
        self::assertSame(SectionReferenceStyle::Explicit, $options->sectionReferences());
        self::assertSame(OrderingPolicy::SourceOrder, $options->ordering());
        self::assertSame(ExtendsPathStyle::HiraethFile, $options->extendsPathStyle());
    }

    public function testDotinkV1UsesBareRelativeExtendsPaths(): void
    {
        self::assertSame(ExtendsPathStyle::BareRelative, DotinkStyle::v1()->extendsPathStyle());
    }

    public function testWithMethodsReturnNewOptionsWithoutChangingTheProfileDefault(): void
    {
        $default = ImarcStyle::v1();
        $changed = $default->withIndentation('  ');

        self::assertNotSame($default, $changed);
        self::assertSame("\t", $default->indentation());
        self::assertSame('  ', $changed->indentation());
        self::assertSame("\t", ImarcStyle::v1()->indentation());
    }

    public function testItConfiguresLeadingSpacingImmutably(): void
    {
        $default = ImarcStyle::v1();
        $changed = $default->withSpacingPolicy(SpacingPolicy::None);

        self::assertSame(SpacingPolicy::Preserve, $default->spacingPolicy());
        self::assertSame(SpacingPolicy::None, $changed->spacingPolicy());
    }

    public function testEveryFormattingChoiceCanBeChangedWithoutMutatingTheOriginal(): void
    {
        $original = new FormatOptions();
        $changes = [
            [$original->withLineEnding(LineEnding::CrLf), 'lineEnding', LineEnding::Lf, LineEnding::CrLf],
            [$original->withLeadingComments(LeadingCommentPolicy::WinnerOnly), 'leadingComments', LeadingCommentPolicy::NearestDefinition, LeadingCommentPolicy::WinnerOnly],
            [$original->withOrdering(OrderingPolicy::SourceOrder), 'ordering', OrderingPolicy::CanonicalSections, OrderingPolicy::SourceOrder],
            [$original->withExtendsPathStyle(ExtendsPathStyle::BareRelative), 'extendsPathStyle', ExtendsPathStyle::HiraethFile, ExtendsPathStyle::BareRelative],
            [$original->withNumericStyle(NumericStyle::Preserve), 'numericStyle', NumericStyle::Decimal, NumericStyle::Preserve],
            [$original->withStringQuoting(StringQuoting::Always), 'stringQuoting', StringQuoting::MinimalSafe, StringQuoting::Always],
            [$original->withSectionReferences(SectionReferenceStyle::Preserve), 'sectionReferences', SectionReferenceStyle::Explicit, SectionReferenceStyle::Preserve],
            [$original->withSpacingPolicy(SpacingPolicy::One), 'spacingPolicy', SpacingPolicy::Preserve, SpacingPolicy::One],
        ];

        foreach ($changes as [$changed, $accessor, $before, $after]) {
            self::assertNotSame($original, $changed);
            self::assertSame($before, $original->$accessor());
            self::assertSame($after, $changed->$accessor());
        }
    }

    public function testV2ProfilesExposeCanonicalOrdering(): void
    {
        self::assertSame(OrderingPolicy::CanonicalSections, ImarcStyle::v2()->ordering());
        self::assertSame(ExtendsPathStyle::BareRelative, DotinkStyle::v2()->extendsPathStyle());
    }
}
