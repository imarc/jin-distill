<?php

namespace JinDistill\Formatting;

use InvalidArgumentException;

final class FormatOptions
{
    private bool $legacyCommentMetadata = false;

    public function __construct(
        private string $indentation = "\t",
        private LineEnding $lineEnding = LineEnding::Lf,
        private LeadingCommentPolicy $leadingComments = LeadingCommentPolicy::NearestDefinition,
        private OrderingPolicy $ordering = OrderingPolicy::CanonicalSections,
        private InheritancePathStyle $extendsPathStyle = InheritancePathStyle::HiraethFile,
        private NumericStyle $numericStyle = NumericStyle::Decimal,
        private StringQuoting $stringQuoting = StringQuoting::MinimalSafe,
        private SectionReferenceStyle $sectionReferences = SectionReferenceStyle::Explicit,
        private SpacingPolicy $spacingPolicy = SpacingPolicy::Preserve,
    ) {
        if ($indentation === '') {
            throw new InvalidArgumentException('Formatting indentation must not be empty.');
        }
    }

    public static function legacyV1(): self
    {
        $options = new self();
        $options->legacyCommentMetadata = true;
        return $options;
    }

    public function usesLegacyCommentMetadata(): bool
    {
        return $this->legacyCommentMetadata;
    }

    public function indentation(): string
    {
        return $this->indentation;
    }
    public function lineEnding(): LineEnding
    {
        return $this->lineEnding;
    }
    public function leadingComments(): LeadingCommentPolicy
    {
        return $this->leadingComments;
    }
    public function ordering(): OrderingPolicy
    {
        return $this->ordering;
    }
    public function extendsPathStyle(): InheritancePathStyle
    {
        return $this->extendsPathStyle;
    }
    public function numericStyle(): NumericStyle
    {
        return $this->numericStyle;
    }
    public function stringQuoting(): StringQuoting
    {
        return $this->stringQuoting;
    }
    public function sectionReferences(): SectionReferenceStyle
    {
        return $this->sectionReferences;
    }
    public function spacingPolicy(): SpacingPolicy
    {
        return $this->spacingPolicy;
    }

    public function withIndentation(string $indentation): self
    {
        if ($indentation === '') {
            throw new InvalidArgumentException('Formatting indentation must not be empty.');
        }
        $copy = clone $this;
        $copy->indentation = $indentation;
        return $copy;
    }

    public function withLineEnding(LineEnding $lineEnding): self
    {
        $copy = clone $this;
        $copy->lineEnding = $lineEnding;
        return $copy;
    }

    public function withLeadingComments(LeadingCommentPolicy $leadingComments): self
    {
        $copy = clone $this;
        $copy->leadingComments = $leadingComments;
        return $copy;
    }

    public function withOrdering(OrderingPolicy $ordering): self
    {
        $copy = clone $this;
        $copy->ordering = $ordering;
        return $copy;
    }

    public function withExtendsPathStyle(InheritancePathStyle $extendsPathStyle): self
    {
        $copy = clone $this;
        $copy->extendsPathStyle = $extendsPathStyle;
        return $copy;
    }

    public function withNumericStyle(NumericStyle $numericStyle): self
    {
        $copy = clone $this;
        $copy->numericStyle = $numericStyle;
        return $copy;
    }

    public function withStringQuoting(StringQuoting $stringQuoting): self
    {
        $copy = clone $this;
        $copy->stringQuoting = $stringQuoting;
        return $copy;
    }

    public function withSectionReferences(SectionReferenceStyle $sectionReferences): self
    {
        $copy = clone $this;
        $copy->sectionReferences = $sectionReferences;
        return $copy;
    }

    public function withSpacingPolicy(SpacingPolicy $spacingPolicy): self
    {
        $copy = clone $this;
        $copy->spacingPolicy = $spacingPolicy;
        return $copy;
    }
}
