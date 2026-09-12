<?php

namespace JinDistill\Formats;

class CsvFormat implements FormatInterface
{
    /**
     * The data to be output
     * @var string
     */
    protected string $data;


    /**
     * @param string        $delimiter  The delimiter to use between fields
     * @param list<string>  $header     The header row to output, leave empty for no header
     * @param bool          $extensions Whether to output the --extends field
     * @param list<string>  $excludes   Fields (and children) to exclude from the output
     * @param list<string>  $cloak      Strings within field names to "hide" from the output
     * @param array<string, string> $mappings Map config values to new values
     */
    public function __construct(
        protected string $delimiter  = ',',
        protected array  $header     = ['Field', 'Default Value'],
        protected bool   $extensions = true,
        protected array  $excludes   = [],
        protected array  $cloak      = [],
        protected array  $mappings   = [],
    ) {
        $this->data = '';
    }


    /**
     *
     */
    public function encode(array $data): string
    {
        if (!empty($this->header)) {
            $this->outputHeader($this->header);
        }

        if (isset($data['--extends'])) {
            if ($this->extensions) {
                $extends = $data['--extends'];
                $this->write(['Extends', is_scalar($extends) ? (string) $extends : '']);
            }
            unset($data['--extends']);
        }

        foreach ($data as $key => $value) {
            $this->handle((string) $key, $value);
        }

        return $this->data;
    }


    /**
     *
     */
    public function handle(string $key, mixed $value): void
    {
        if (in_array($key, $this->excludes, true)) {
            return;
        }

        if ($this->valueType($value) === 'primitive') {
            $this->write([trim(str_replace($this->cloak, '', $key), '.'), $this->encodeValue($value)]);

            return;
        }

        foreach ((array) $value as $childKey => $childValue) {
            $this->handle(sprintf('%s.%s', $key, (string) $childKey), $childValue);
        }
    }


    /**
     *
     */
    protected function valueType(mixed $value): string
    {
        if (is_array($value)) {
            return 'array';
        }
        if (is_object($value)) {
            return 'object';
        }
        return 'primitive';
    }


    /**
     * Encode Values
     */
    protected function encodeValue(mixed $value): string
    {
        $encoded = json_encode($value, JSON_THROW_ON_ERROR);

        return $this->mappings[$encoded] ?? $encoded;
    }


    /**
     *
     */
    /** @param list<string> $array */
    protected function outputHeader(array $array): void
    {
        $this->write($array);
    }


    /**
     *
     */
    /** @param list<string> $data */
    protected function write(array $data): void
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new \RuntimeException('Cannot open a temporary CSV buffer.');
        }

        fputcsv($handle, $data, $this->delimiter, '"');
        rewind($handle);
        $row = fread($handle, 1048576);
        fclose($handle);

        $this->data .= rtrim($row === false ? '' : $row) . "\n";
    }
}
