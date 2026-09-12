<?php

namespace JinDistill\Tests\Diff;

use JinDistill\Diff\DiffOptions;
use JinDistill\Diff\DiffResult;
use JinDistill\Evaluation\EvaluationOptions;
use JinDistill\Evaluation\SemanticVerifier;
use JinDistill\JinDistiller;
use PHPUnit\Framework\TestCase;

final class DiffExamplesTest extends TestCase
{
    private const EXAMPLES = __DIR__ . '/../../examples/diff';

    public function testIdenticalDefinitionsGenerateOnlyCanonicalExtends(): void
    {
        $result = $this->diff('a1', 'a2', 'a3');

        self::assertSame(file_get_contents(self::EXAMPLES . '/a3.jin'), $result->content());
        self::assertSame([], $result->removals());
    }

    public function testDivergentDefinitionsGenerateMinimumInheritanceDocument(): void
    {
        $result = $this->diff('b1', 'b2', 'b3');

        self::assertSame(file_get_contents(self::EXAMPLES . '/b3.jin'), $result->content());
        self::assertSame(
            ['search.parameters', 'cancellation', 'tracker.context_selector'],
            array_map(static fn ($path): string => implode('.', $path->segments()), $result->removals()),
        );
    }

    public function testChangedObjectsCopyEveryTargetMember(): void
    {
        $content = $this->diff('b1', 'b2', 'b3')->content();

        self::assertStringContainsString("\"step\": 10,\n\t\t\"min\": 0,\n\t\t\"max\": 100,\n\t\t\"level\": \"basic\",", $content);
    }

    public function testItPreservesTargetCommentsAndSectionOrder(): void
    {
        $content = $this->diff('b1', 'b2', 'b3')->content();

        self::assertStringContainsString("; Child search form\nname = Child Form\n\n[search]\n", $content);
    }

    public function testItLeavesExampleSourcesUnchanged(): void
    {
        $before = $this->hashes();

        $this->diff('b1', 'b2', 'b3');

        self::assertSame($before, $this->hashes());
    }

    public function testGeneratedDocumentOverlaysParentOntoTarget(): void
    {
        $verification = $this->diff('b1', 'b2', 'b3', (new DiffOptions())->withVerification(true))->verification();

        self::assertSame([], $verification?->differences());
    }

    public function testGeneratedDocumentResolvesIdenticallyUnderDotink(): void
    {
        $options = new EvaluationOptions([], ['file' => static fn (string $path): string => self::EXAMPLES . '/' . $path]);

        $verification = (new SemanticVerifier())->verify(
            file_get_contents(self::EXAMPLES . '/b2.jin'),
            $this->diff('b1', 'b2', 'b3')->content(),
            $options,
        );

        self::assertTrue($verification->isEquivalent(), implode(', ', $verification->differences()));
    }

    private function diff(string $parent, string $target, string $output, ?DiffOptions $options = null): DiffResult
    {
        return (new JinDistiller())->diffFiles(
            self::EXAMPLES . '/' . $parent . '.jin',
            self::EXAMPLES . '/' . $target . '.jin',
            self::EXAMPLES . '/' . $output . '.jin',
            $options,
        );
    }

    /** @return array<string, string> */
    private function hashes(): array
    {
        $hashes = [];
        foreach (glob(self::EXAMPLES . '/*.jin') as $path) {
            $hashes[basename($path)] = hash_file('sha256', $path);
        }

        return $hashes;
    }
}
