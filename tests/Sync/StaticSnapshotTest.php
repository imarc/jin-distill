<?php

namespace JinDistill\Tests\Sync;

use InvalidArgumentException;
use JinDistill\Evaluation\JinEvaluator;
use JinDistill\JinDistiller;
use JinDistill\Sync\StaticSnapshot;
use PHPUnit\Framework\TestCase;

final class StaticSnapshotTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            unlink($file);
        }
    }

    private function source(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'jin-lock-');
        self::assertIsString($path);
        file_put_contents($path, $contents);
        $this->files[] = $path;
        return $path;
    }

    public function testCaptureRoundTripAndTypedValues(): void
    {
        $first = $this->source("emptyMap = {}\nemptyList = []\nnumber = 1\nvalue = null\nmap = {\"0\": {\"a.b\": true, \"~/\": false}}\nfunction = env(\"X\")");
        $second = $this->source("; comment\nvalue = null\nnumber = 0x1\nemptyList = []\nemptyMap = {}\nmap = {\"0\": {\"~/\": false, \"a.b\": true}}\nfunction = env(\"X\")");
        $distiller = new JinDistiller();
        $before = $distiller->snapshotFiles(['forms' => $first]);
        $after = $distiller->snapshotFiles(['forms' => $second]);

        self::assertSame('unchanged', StaticSnapshot::fromJson($before->toJson())->compare($after)->status());
        self::assertStringContainsString('"entries": {}', $before->toJson());
        self::assertStringContainsString('"items": []', $before->toJson());

        file_put_contents($second, "emptyMap = []\nemptyList = {}\nnumber = \"1\"\nmap = {\"0\": {\"a.b\": false, \"~/\": false}}\nfunction = env(\"Y\")");
        $changes = $before->compare($distiller->snapshotFiles(['forms' => $second]))->changes();
        self::assertSame(['/emptyList', '/emptyMap', '/function', '/map/0/a.b', '/number', '/value'], array_map(static fn ($change): string => $change->path(), $changes));
        self::assertSame('removed', $changes[5]->kind());
        self::assertSame(['type' => 'null'], $changes[5]->previous());
    }

    public function testListRulesAndRootStatus(): void
    {
        $path = $this->source('items = ["a", "b"]');
        $distiller = new JinDistiller();
        $before = $distiller->snapshotFiles(['one' => $path]);
        file_put_contents($path, 'items = ["a", "b", "c"]');
        $appended = $distiller->snapshotFiles(['one' => $path]);
        self::assertSame('additive', $before->compare($appended)->status());
        self::assertSame('/items/2', $before->compare($appended)->changes()[0]->path());
        self::assertSame('removed', $appended->compare($before)->status());
        file_put_contents($path, 'items = ["x", "a", "b"]');
        $prepended = $distiller->snapshotFiles(['one' => $path]);
        self::assertSame('updated', $before->compare($prepended)->status());
        self::assertSame('/items', $before->compare($prepended)->changes()[0]->path());

        $other = $this->source('enabled = true');
        $mixed = $before->compare($distiller->snapshotFiles(['one' => $path, 'two' => $other]));
        self::assertSame('mixed', $mixed->status());
        self::assertSame(['one' => 'updated', 'two' => 'additive'], $mixed->rootStatuses());
        self::assertSame('removed', $distiller->snapshotFiles(['one' => $path, 'two' => $other])->compare($prepended)->rootStatuses()['two']);
    }

    public function testDottedPathsAndPointerEscaping(): void
    {
        $path = $this->source('settings = {"a.b": {"~/": true}}');
        $distiller = new JinDistiller();
        $before = $distiller->snapshotFiles(['root' => $path]);
        file_put_contents($path, 'settings = {"a.b": {"~/": false}}');
        $change = $before->compare($distiller->snapshotFiles(['root' => $path]))->changes()[0];
        self::assertSame('/settings/a.b/~0~1', $change->path());
        self::assertSame(['type' => 'boolean', 'value' => true], $change->previous());

        file_put_contents($path, "[settings]\nactive = true");
        $dotted = $distiller->snapshotFiles(['root' => $path]);
        file_put_contents($path, 'settings = {"active": true}');
        self::assertSame('unchanged', $dotted->compare($distiller->snapshotFiles(['root' => $path]))->status());
    }

    public function testListMiddleChangesAndOpaqueText(): void
    {
        $path = $this->source("items = [1, 2, 3]\nsecret = env(\"X\")");
        $distiller = new JinDistiller();
        $before = $distiller->snapshotFiles(['root' => $path]);
        file_put_contents($path, "items = [1, 3]\nsecret = env( \"X\" )");
        $changes = $before->compare($distiller->snapshotFiles(['root' => $path]))->changes();
        self::assertSame(['/items', '/secret'], array_map(static fn ($change): string => $change->path(), $changes));
        self::assertSame(['type' => 'opaque', 'value' => 'env("X")'], $changes[1]->previous());
    }

    public function testNumericLookingStringsCompareStrictly(): void
    {
        $path = $this->source('code = "01"');
        $distiller = new JinDistiller();
        $before = $distiller->snapshotFiles(['root' => $path]);
        file_put_contents($path, 'code = "1"');
        self::assertSame('updated', $before->compare($distiller->snapshotFiles(['root' => $path]))->status());
    }

    public function testInheritanceAndDirectives(): void
    {
        $parent = $this->source("values = {\"keep\": 1, \"remove\": 2}\nextra = false");
        $child = $this->source("--extends = $parent\n--without = values.remove\nvalues = {\"keep\": 3}");
        $distiller = new JinDistiller();
        $before = $distiller->snapshotFiles(['child' => $child]);
        file_put_contents($parent, "values = {\"keep\": 9, \"remove\": 2}\nextra = false\nnew = true");
        $after = $distiller->snapshotFiles(['child' => $child]);
        self::assertSame('additive', $before->compare($after)->status());
        self::assertSame('/new', $before->compare($after)->changes()[0]->path());
        file_put_contents($child, "--extends = $parent\n--without = values.remove\nvalues = {\"keep\": 4}");
        self::assertSame('/values/keep', $after->compare($distiller->snapshotFiles(['child' => $child]))->changes()[0]->path());
    }

    public function testRejectsIncompleteSourcesAndInvalidLocks(): void
    {
        $path = $this->source('--extends = env("PARENT")');
        $distiller = new JinDistiller();
        try {
            $distiller->snapshotFiles(['root' => $path]);
            self::fail('Dynamic inheritance must fail.');
        } catch (InvalidArgumentException) {
        }
        file_put_contents($path, '--without = env("KEY")');
        $this->expectException(InvalidArgumentException::class);
        $distiller->snapshotFiles(['root' => $path]);
    }

    public function testRelativePathsAndOpaqueValuesDoNotEvaluate(): void
    {
        $relative = 'tests/fixtures/base.jin';
        $absolute = realpath($relative);
        self::assertIsString($absolute);
        $distiller = (new JinDistiller())->withEvaluator(new JinEvaluator(static function (): never {
            throw new \RuntimeException('Evaluator was called.');
        }));
        self::assertSame('unchanged', $distiller->snapshotFiles(['root' => $relative])->compare($distiller->snapshotFiles(['root' => $absolute]))->status());
        $opaque = $this->source('secret = env("TOKEN")');
        self::assertStringContainsString('"opaque"', $distiller->snapshotFiles(['root' => $opaque])->toJson());
    }

    public function testRejectsMalformedLockNodes(): void
    {
        $rejected = 0;
        foreach ([
            '{"schema":"bad","roots":{"x":{"type":"null"}}}',
            '{"schema":"jin-distill-static-sync-lock/1","roots":{"x":{"type":"float","value":1}}}',
            '{"schema":"jin-distill-static-sync-lock/1","roots":{"x":{"type":"map","entries":[]}}}',
            '{"schema":"jin-distill-static-sync-lock/1","roots":{"x":{"type":"unknown","value":1}}}',
            '{"schema":"jin-distill-static-sync-lock/1","roots":{"x":{"type":"null","locations":{"/bad~2path":{"source":"a.jin","line":0}}}}}',
        ] as $json) {
            try {
                StaticSnapshot::fromJson($json);
                self::fail('Malformed lock accepted.');
            } catch (InvalidArgumentException) {
                $rejected++;
            }
        }
        self::assertSame(5, $rejected);
    }
}
