<?php

namespace JinDistill\Syntax;

use JinDistill\JinDocument;
use JinDistill\Source\SourceId;

final class Document extends JinDocument
{
    /**
     * @param list<Statement> $statements
     * @param array<string, string> $numericLexemes
     */
    public function __construct(private array $statements, private SourceId $source, array $data = [], array $directives = [], array $metadata = [], private ?string $contents = null, private array $numericLexemes = [])
    {
        parent::__construct($data, $directives, $metadata, $source->canonicalPath());
    }

    /** Original bytes this document was decoded from, when they are known. */
    public function contents(): ?string
    {
        return $this->contents;
    }

    /** @return list<Statement> */
    public function statements(): array
    {
        return $this->statements;
    }

    public function source(): SourceId
    {
        return $this->source;
    }

    /** @return array<string, string> */
    public function numericLexemes(): array
    {
        return $this->numericLexemes;
    }
}
