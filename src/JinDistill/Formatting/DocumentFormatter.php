<?php

namespace JinDistill\Formatting;

use JinDistill\Source\Path;
use JinDistill\Syntax\Assignment;
use JinDistill\Syntax\BlankLine;
use JinDistill\Syntax\Comment;
use JinDistill\Syntax\Document;
use JinDistill\Syntax\Section;
use JinDistill\Syntax\Statement;

final class DocumentFormatter
{
    public function order(Document $document, OrderingPolicy $policy): Document
    {
        if ($policy === OrderingPolicy::SourceOrder) {
            return $document;
        }

        $directives = [];
        $roots = [];
        $sections = [];
        $sectionNodes = [];
        $pending = [];
        $originalSectionOrder = [];

        foreach ($document->statements() as $statement) {
            if ($statement instanceof BlankLine || $statement instanceof Comment) {
                $pending[] = $statement;
                continue;
            }

            if ($statement instanceof Section) {
                $key = $statement->path()->toJsonPointer();
                $originalSectionOrder[] = $key;
                if (!isset($sectionNodes[$key])) {
                    $sectionNodes[$key] = [...$pending, $statement];
                    $pending = [];
                }
                $sections[$key] ??= [];
                continue;
            }

            if (!$statement instanceof Assignment) {
                $pending = [];
                continue;
            }

            $nodes = [...$pending, $statement];
            $pending = [];
            $segments = $statement->path()->segments();
            if (str_starts_with($segments[0], '--')) {
                array_push($directives, ...$nodes);
            } elseif (count($segments) === 1) {
                array_push($roots, ...$nodes);
            } else {
                $owner = Path::fromSegments(array_slice($segments, 0, -1));
                $key = $owner->toJsonPointer();
                $sections[$key] ??= [];
                $sectionNodes[$key] ??= [new Section($owner, implode('.', $owner->segments()), $statement->span())];
                array_push($sections[$key], ...$nodes);
            }
        }

        $ordered = [...$directives, ...$roots];
        foreach ($sections as $key => $assignments) {
            array_push($ordered, ...$sectionNodes[$key], ...$assignments);
        }

        if ($originalSectionOrder !== array_keys($sections)) {
            $ordered = array_map(static function (Statement $statement): Statement {
                if (!$statement instanceof Section || !str_starts_with($statement->lexeme(), '&')) {
                    return $statement;
                }
                $path = $statement->path();
                return new Section($path, implode('.', $path->segments()), $statement->span());
            }, $ordered);
        }

        return new Document($ordered, $document->source(), $document->data, $document->directives, $document->metadata, $document->contents(), $document->numericLexemes());
    }
}
