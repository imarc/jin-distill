<?php

namespace JinDistill\Evaluation;

use Dotink\Jin\Parser;

final class SemanticVerifier
{
    public function verify(string $original, string $generated, EvaluationOptions $options): VerificationResult
    {
        $before = $this->evaluate($original, $options);
        $after = $this->evaluate($generated, $options);
        $differences = [];
        $this->compare($before, $after, '', $differences);

        return new VerificationResult($differences);
    }

    private function evaluate(string $contents, EvaluationOptions $options): mixed
    {
        $parser = new Parser($options->context(), $options->functions(), $options->associative());

        return $parser->parse($contents)->all();
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
