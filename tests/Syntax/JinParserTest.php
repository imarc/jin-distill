<?php

namespace JinDistill\Tests\Syntax;

use JinDistill\Decoders\JinDecoder;
use JinDistill\Source\SourceId;
use JinDistill\Syntax\Assignment;
use JinDistill\Syntax\BlankLine;
use JinDistill\Syntax\Comment;
use JinDistill\Syntax\Document;
use JinDistill\Syntax\Section;
use PHPUnit\Framework\TestCase;

final class JinParserTest extends TestCase
{
    public function testItRetainsOrderedStatementsWhenSectionsAreReopened(): void
    {
        $document = (new JinDecoder())->decode(<<<'JIN'
; Document title
title = First

[form]
    name = CPA ; public label

[form]
    enabled = true
JIN, new SourceId('memory://ordered.jin', 'ordered.jin'));

        self::assertInstanceOf(Document::class, $document);
        $statements = $document->statements();
        self::assertInstanceOf(Comment::class, $statements[0]);
        self::assertInstanceOf(Assignment::class, $statements[1]);
        self::assertInstanceOf(BlankLine::class, $statements[2]);
        self::assertInstanceOf(Section::class, $statements[3]);
        self::assertInstanceOf(Assignment::class, $statements[4]);
        self::assertInstanceOf(BlankLine::class, $statements[5]);
        self::assertInstanceOf(Section::class, $statements[6]);
        self::assertInstanceOf(Assignment::class, $statements[7]);
        self::assertSame(['form'], $statements[3]->path()->segments());
        self::assertSame(['form', 'name'], $statements[4]->path()->segments());
        self::assertSame(['form'], $statements[6]->path()->segments());
        self::assertSame(['form', 'enabled'], $statements[7]->path()->segments());
        self::assertSame('public label', $statements[4]->inlineComment()?->text());
        self::assertSame(['Document title'], array_map(
            static fn (Comment $comment): string => $comment->text(),
            $statements[1]->comments(),
        ));
    }

    public function testItKeepsLiteralDottedJsonKeysSeparateFromAssignmentPaths(): void
    {
        $document = (new JinDecoder())->decode(<<<'JIN'
[form]
    fields = {
        "person.name": true,
    }
JIN);

        $assignment = $document->statements()[1];

        self::assertInstanceOf(Assignment::class, $assignment);
        self::assertSame(['form', 'fields'], $assignment->path()->segments());
        self::assertSame(['person.name' => true], $assignment->value()->staticValue());
    }

    public function testItPreservesCommentsAndSourceSpans(): void
    {
        $document = (new JinDecoder())->decode(<<<'JIN'
; Leading comment
value = true ; Inline comment
JIN, new SourceId('memory://spans.jin', 'spans.jin'));

        $comment = $document->statements()[0];
        $assignment = $document->statements()[1];

        self::assertInstanceOf(Comment::class, $comment);
        self::assertSame('Leading comment', $comment->text());
        self::assertSame([
            'source' => 'memory://spans.jin',
            'reference' => 'spans.jin',
            'start' => ['line' => 1, 'column' => 1],
            'end' => ['line' => 1, 'column' => 17],
        ], $comment->span()->toArray());
        self::assertSame('Inline comment', $assignment->inlineComment()?->text());
        self::assertSame(2, $assignment->span()->toArray()['start']['line']);
    }

    public function testItResolvesSectionReferencesWithoutDiscardingTheirLexemes(): void
    {
        $document = (new JinDecoder())->decode(<<<'JIN'
[reference]
    [&.sub]
        first = one
        [&&.nested]
            second = two
JIN);

        $statements = $document->statements();

        self::assertSame('&.sub', $statements[1]->lexeme());
        self::assertSame(['reference', 'sub'], $statements[1]->path()->segments());
        self::assertSame(['reference', 'sub', 'first'], $statements[2]->path()->segments());
        self::assertSame('&&.nested', $statements[3]->lexeme());
        self::assertSame(['reference', 'sub', 'nested', 'second'], $statements[4]->path()->segments());
    }

    public function testItClassifiesStaticScalarsAndJsonValuesWithoutEvaluation(): void
    {
        $document = (new JinDecoder())->decode(<<<'JIN'
nullValue = null
boolValue = true
hexValue = 0xD
binaryValue = 0b1101
octalValue = 015
objectValue = {"name": "CPA",}
JIN);

        $values = array_map(
            static fn (Assignment $assignment): mixed => $assignment->value()->staticValue(),
            $document->statements(),
        );

        self::assertSame([null, true, 13, 13, 13, ['name' => 'CPA']], $values);
        foreach ($document->statements() as $statement) {
            self::assertTrue($statement->value()->isStaticallyKnown());
        }
    }

    public function testItPreservesRawMultilineValues(): void
    {
        $document = (new JinDecoder())->decode("message = A message that continues\nwithout being evaluated.\n");

        $assignment = $document->statements()[0];

        self::assertSame("A message that continues\nwithout being evaluated.", $assignment->value()->raw());
        self::assertSame("A message that continues\nwithout being evaluated.", $assignment->value()->staticValue());
    }

    public function testItKeepsFunctionAndTemplateValuesOpaque(): void
    {
        $document = (new JinDecoder())->decodeFile(__DIR__ . '/../fixtures/dotink-4.9-sample.jin');

        $opaqueAssignments = array_filter(
            $document->statements(),
            static fn (mixed $statement): bool => $statement instanceof Assignment && !$statement->value()->isStaticallyKnown(),
        );

        self::assertCount(6, $opaqueAssignments);
        foreach ($opaqueAssignments as $statement) {
            self::assertInstanceOf(Assignment::class, $statement);
            self::assertFalse($statement->value()->isStaticallyKnown());
            self::assertNull($statement->value()->staticValue());
            self::assertNotSame('', $statement->value()->raw());
        }
    }
}
