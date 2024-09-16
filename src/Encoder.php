<?php

class Encoder
{
    public $handler, $outputHtml;

    /**
     * 
     */
    public function __construct($outputHtml = FALSE)
    {
        $this->handler = fopen("php://output" ,"w");
        $this->outputHtml = $outputHtml;
    }
    /**
     * 
     */
    public function encode($array)
    {
        if (isset($array["--extends"])) {
            $extends = $array["--extends"];
            unset($array["--extends"]);
        }
        if ($this->outputHtml) {
            fwrite($this->handler, '<pre>');
        }
        if (isset($extends)) {
            fwrite($this->handler, sprintf("--extends = file(%s)\n\n", $extends));
        }

        foreach ($array as $key => $value)
        {
            $this->encodeLine($key, $value);
        }
        if ($this->outputHtml) {
            fwrite($this->handler, '</pre>');
        }
    }


    /**
     * 
     */
    public function encodeLine($key, $value, $depth = 0)
    {
        for($i = 0; $i < $depth; $i++) {
            fwrite($this->handler, "\t");
        }

        switch ($depth) {
            case 0:
                fwrite($this->handler, sprintf("[%s] \n", $key));
                break;
            case 1:
                fwrite($this->handler, sprintf("%s = ", $key));
                break;
            default:
                fwrite($this->handler, sprintf('"%s": ', $key));
                break;
        }

        if (is_array($value)) {
            if ($depth >= 1) {
                fwrite($this->handler, "{\n");
            }
            foreach ($value as $k => $v) {
                $this->encodeLine($k, $v, $depth + 1);
            }
            if ($depth >= 1) {
                for($i = 0; $i < $depth; $i++) {
                    fwrite($this->handler, "\t");
                }
                if ($depth >= 2) {
                    fwrite($this->handler, "},\n");
                } else {
                    fwrite($this->handler, "}\n");
                }
            }

        } else {
            fwrite($this->handler, $this->encodeValue($value));
            if ($depth > 1) {
                fwrite($this->handler, ",");
            }
            fwrite($this->handler, "\n");
        }
    }

    /**
     * 
     */
    protected function encodeValue($value)
    {
        if ($value === NULL) {
            return "null";
        }

        if (is_bool($value)) {
            return $value ? "true" : "false";
        }

        return sprintf('"%s"', $value);
    }
}