<?php

class Encoder
{
    /**
     * The 
     * @var 
     */
    protected $file;
    

    /**
     * Output format
     * @var string
     */
    protected $format;


    /** 
     * Where .ini becomes .json
     * @var int 
     */ 
    protected $boundary = 1;

    
    /**
     * @var array
     */
    protected $data;


    protected $tabs = "\t";


    /**
     * 
     */
    public function __construct($format)
    {
        $this->file = fopen("php://output" ,"w");
        $this->format = $format;
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
        // if ($this->outputHtml) {
        //     fwrite($this->file, '<pre>');
        // }
        if (isset($extends)) {
            fwrite($this->file, sprintf("--extends = file(%s)\n\n", $extends));
        }

        foreach ($array as $key => $value) {
            $this->handle($key, $value);
        }
        if ($this->outputHtml) {
            fwrite($this->file, '</pre>');
        }
    }


    /**
     * 
     */
    protected function handle($key, $value, $depth = 0, $context = null)
    {
        for ($i = 0; $i < $depth; $i++) {
            $this->write($this->tabs);
        }

        if ($depth == 0) {
            if (is_array($value)) {
                $this->write(sprintf("[%s]\n", $key));
                foreach ($value as $k => $v) {
                    $this->handle($k, $v, $depth + 1);
                }
            } else {
                $this->write(sprintf("%s = %s\n", $key, $this->encodeValue($value)));
            }
        } else {
            switch ($context) {
                case 'array':
                    if (is_array($value)) {
                        $this->write(sprintf("[\n"));
                        foreach ($value as $k => $v) {
                            $this->handle($k, $v, $depth + 1, 'array');
                        }
                        $this->write("],\n");
                    } else {
                        $this->write(sprintf("%s,\n", $this->encodeValue($value)));
                    }
                    break;
                case 'object':
                    if (is_array($value)) {
                        $this->write(sprintf("{\n"));
                        foreach ($value as $k => $v) {
                            $this->handle($k, $v, $depth + 1, 'object');
                        }
                        $this->write("},\n");
                    } else {
                        $this->write(sprintf("\"%s\": %s,\n", $key, $this->encodeValue($value)));
                    }
                    break;
                default:
                    if ($depth > $this->boundary) {
                        if (is_array($value)) {

                        } else {

                        }

                    } else {
                        $this->write(sprintf("%s = ", $key));
                    }
                    break;
            }
        }
    }

    protected function handleArray($key, $value, $depth)
    {
        if (array_is_list($value)) {
            $this->write(sprintf("\"%s\": [\n", $key));
            foreach ($value as $k => $v) {
                $this->handle($k, $v, $depth + 1, 'array');
            }
            $this->write("]\n");
        } else {
            $this->write(sprintf("\"%s\": {\n", $key));
            foreach ($value as $k => $v) {
                $this->handle($k, $v, $depth + 1, 'object');
            }
            $this->write("}\n");
        }
    }

    protected function encodeLine($key, $value, $depth = 0)
    {
        for($i = 0; $i < $depth; $i++) {
            fwrite($this->file, "\t");
        }

        switch ($depth) {
            case 0:
                fwrite($this->file, sprintf("[%s] \n", $key));
                break;
            case 1:
                fwrite($this->file, sprintf("%s = ", $key));
                break;
            default:
                fwrite($this->file, sprintf('"%s": ', $key));
                break;
        }

        if (is_array($value)) {
            if ($depth >= 1) {
                fwrite($this->file, "{\n");
            }
            foreach ($value as $k => $v) {
                $this->handle($k, $v, $depth + 1);
            }
            if ($depth >= 1) {
                for($i = 0; $i < $depth; $i++) {
                    fwrite($this->file, "\t");
                }
                if ($depth >= 2) {
                    fwrite($this->file, "},\n");
                } else {
                    fwrite($this->file, "}\n");
                }
            }

        } else {
            fwrite($this->file, $this->encodeValue($value));
            if ($depth > 1) {
                fwrite($this->file, ",");
            }
            fwrite($this->file, "\n");
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

    /**
     * 
     */
    protected function write(string $output)
    {
        fwrite($this->file, $output);
    }
}