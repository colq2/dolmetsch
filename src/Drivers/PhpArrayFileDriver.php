<?php

namespace colq2\Dolmetsch\Drivers;

class PhpArrayFileDriver implements TranslationFileDriver
{
    public function read(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $data = include $path;

        return is_array($data) ? $data : [];
    }

    public function write(string $path, array $data): void
    {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, "<?php\nreturn ".$this->export($data, 0).";\n");
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function export(array $data, int $depth): string
    {
        $indent = str_repeat('    ', $depth + 1);
        $closingIndent = str_repeat('    ', $depth);

        $lines = [];

        foreach ($data as $key => $value) {
            $exportedKey = is_int($key) ? (string) $key : $this->quote((string) $key);
            $exportedValue = is_array($value) ? $this->export($value, $depth + 1) : $this->exportScalar($value);

            $lines[] = "{$indent}{$exportedKey} => {$exportedValue}";
        }

        if ($lines === []) {
            return '[]';
        }

        return "[\n".implode(",\n", $lines)."\n{$closingIndent}]";
    }

    private function exportScalar(mixed $value): string
    {
        return match (true) {
            is_string($value) => $this->quote($value),
            is_bool($value) => $value ? 'true' : 'false',
            is_null($value) => 'null',
            default => (string) $value,
        };
    }

    private function quote(string $value): string
    {
        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }
}
