<?php

namespace JinDistill;

use JinDistill\Decoders\JinDecoder;
use JinDistill\Formats\JinFormat;
use JinDistill\Analysis\AnalysisResult;
use JinDistill\Analysis\Analyzer;
use JinDistill\Analysis\SourceGraphBuilder;
use JinDistill\Evaluation\DotinkEvaluator;
use JinDistill\Evaluation\EvaluationOptions;
use JinDistill\Evaluation\SemanticVerifier;
use JinDistill\Evaluation\VerificationResult;
use JinDistill\Formatting\Normalizer;
use JinDistill\Source\FilesystemSourceLoader;
use JinDistill\Source\FunctionExtendsResolver;
use JinDistill\Source\PathPolicy;
use JinDistill\Source\RelativeExtendsResolver;

class JinDistiller
{
    protected JinDecoder $decoder;
    protected JinResolver $resolver;
    protected JinFormat $format;

    public function __construct(
        ?JinDecoder $decoder = null,
        ?JinResolver $resolver = null,
        ?JinFormat $format = null,
    ) {
        $this->decoder = $decoder ?? new JinDecoder();
        $this->resolver = $resolver ?? new JinResolver($this->decoder);
        $this->format = $format ?? new JinFormat();
    }

    public function decode(string $contents, ?string $path = null): JinDocument
    {
        return $this->decoder->decode($contents, $path);
    }

    public function decodeFile(string $path): JinDocument
    {
        return $this->decoder->decodeFile($path);
    }

    public function resolveFile(string $path): JinDocument
    {
        return $this->resolver->resolveFile($path);
    }

    public function normalize(string $contents, ?string $path = null, bool $extensions = true): string
    {
        return $this->format->encodeDocument($this->decode($contents, $path), $extensions);
    }

    public function normalizeFile(string $path, bool $extensions = true): string
    {
        if (!$extensions) {
            return $this->format->encodeDocument($this->decodeFile($path), false);
        }

        return (new Normalizer($this->analyzerForFile($path)))->normalizeFile($path)->content();
    }

    public function flattenFile(string $path): string
    {
        return $this->format->encodeDocument($this->resolveFile($path), false);
    }

    public function analyzeFile(string $path): AnalysisResult
    {
        return $this->analyzerForFile($path)->analyzeFile($path);
    }

    public function evaluateFile(string $path, ?EvaluationOptions $options = null): AnalysisResult
    {
        return (new DotinkEvaluator($this->analyzerForFile($path)))->evaluateFile($path, $options);
    }

    public function verifySemantics(string $original, string $generated, ?EvaluationOptions $options = null): VerificationResult
    {
        return (new SemanticVerifier())->verify($original, $generated, $options ?? new EvaluationOptions());
    }

    private function analyzerForFile(string $path): Analyzer
    {
        $root = realpath(dirname($path));
        if ($root === false) {
            throw new \RuntimeException(sprintf('Cannot resolve Jin source directory: %s', $path));
        }

        return new Analyzer(new SourceGraphBuilder(
            new FilesystemSourceLoader(new PathPolicy([$root])),
            $this->decoder,
            [new RelativeExtendsResolver(), new FunctionExtendsResolver('file', $root)],
        ));
    }
}
