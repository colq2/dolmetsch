<?php

namespace colq2\Dolmetsch\Drivers;

class JsonFileDriver implements TranslationFileDriver
{
    public function read(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    public function write(string $path, array $data): void
    {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $this->prettyPrint($data)."\n");
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function prettyPrint(array $data): string
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // json_encode's pretty printer always indents with 4 spaces; halve it
        // to match this project's 2-space convention (see resources/js/Lang/en-US.json).
        return preg_replace_callback('/^ +/m', fn (array $matches): string => str_repeat(' ', (int) (strlen($matches[0]) / 2)), (string) $json);
    }
}
