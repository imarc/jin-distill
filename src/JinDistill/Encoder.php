<?php

namespace JinDistill;

use JinDistill\Formats\{FormatInterface};
use JinDistill\Output\OutputInterface;

/**
 * @deprecated 1.0.0 Use a JinDistiller workflow and explicitly persist its returned content.
 */
class Encoder
{
    /**
     * Output format
     * @var FormatInterface
     */
    protected FormatInterface $format;

    /**
     * Output method
     */
    protected OutputInterface $output;


    /**
     *
     */
    public function __construct(FormatInterface $format, OutputInterface $output)
    {
        $this->format = $format;
        $this->output = $output;
    }

    /**
     *
     */
    /** @param array<array-key, mixed> $array */
    public function encode(array $array): void
    {
        $result = $this->format->encode($array);

        $this->output->write($result);
    }
}
