<?php

namespace JinDistill\Evaluation;

final class SemanticVerifier
{
    public function __construct(private ?JinEvaluator $evaluator = null)
    {
    }

    public function verify(
        string $original,
        string $generated,
        ?EvaluationOptions $options = null,
        ?string $originalPath = null,
        ?string $generatedPath = null,
    ): VerificationResult {
        $evaluator = $options === null
            ? ($this->evaluator ?? JinEvaluator::fromOptions())
            : JinEvaluator::fromOptions($options);
        $before = $this->withoutDirectives($evaluator->evaluate($original, $originalPath));
        $after = $this->withoutDirectives($evaluator->evaluate($generated, $generatedPath));
        $differences = [];
        $this->compare($before, $after, '', $differences);

        return new VerificationResult($differences);
    }

    /** Inheritance directives describe composition, never resolved configuration. */
    private function withoutDirectives(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        foreach (array_keys($data) as $key) {
            if (is_string($key) && str_starts_with($key, '--')) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    /** @param list<string> $differences */
    private function compare(mixed $before, mixed $after, string $path, array &$differences): void
    {
        if (is_array($before) && is_array($after)) {
            foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
                $key = (string) $key;
                if (!array_key_exists($key, $before) || !array_key_exists($key, $after)) {
                    $differences[] = $this->pointer($path, $key);
                    continue;
                }
                $this->compare($before[$key], $after[$key], $this->pointer($path, $key), $differences);
            }
            return;
        }
        if ($before !== $after) {
            $differences[] = $path === '' ? '/' : $path;
        }
    }

    private function pointer(string $path, string $segment): string
    {
        return $path . '/' . str_replace(['~', '/'], ['~0', '~1'], $segment);
    }
}
