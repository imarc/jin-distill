<?php
namespace Tumbler;

use Tumbler\Formats\{JinFormat, CsvFormat, FormatInterface};
use Tumbler\Output\OutputInterface;



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
    public function encode($array)
    {
        $result = $this->format->encode($array);

        $this->output->write($result);
    }
}