<?php

namespace JinDistill\Evaluation;

use Closure;
use Dotink\Jin\Parser;

final class JinEvaluator
{
    /** @var Closure(): Parser */
    private Closure $parserFactory;

    /** @param callable(): Parser $parserFactory */
    public function __construct(callable $parserFactory)
    {
        $this->parserFactory = Closure::fromCallable($parserFactory);
    }

    public static function fromOptions(?EvaluationOptions $options = null): self
    {
        $options ??= new EvaluationOptions();

        return new self(static fn (): Parser => new Parser(
            $options->context(),
            $options->functions(),
            $options->associative(),
        ));
    }

    public function evaluate(string $contents, ?string $path = null): mixed
    {
        return ($this->parserFactory)()->parse($contents, $path)->all();
    }
}
