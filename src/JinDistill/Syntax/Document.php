<?php

namespace JinDistill\Syntax;

use JinDistill\JinDocument;
use JinDistill\Source\SourceId;

final class Document extends JinDocument
{
    /** @param list<Statement> $statements */
    public function __construct(private array $statements, private SourceId $source, array $data = [], array $directives = [], array $metadata = [], private ?string $contents = null)
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
}
