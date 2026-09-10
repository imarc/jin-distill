<?php

namespace JinDistill\Syntax;

use JinDistill\JinDocument;
use JinDistill\Source\SourceId;

final class Document extends JinDocument
{
    /** @param list<Statement> $statements */
    public function __construct(private array $statements, private SourceId $source, array $data = [], array $directives = [], array $metadata = [])
    {
        parent::__construct($data, $directives, $metadata, $source->canonicalPath());
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
