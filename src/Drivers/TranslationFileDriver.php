<?php

namespace colq2\Dolmetsch\Drivers;

interface TranslationFileDriver
{
    /**
     * Read a translation file into a nested array. Returns an empty array
     * when the file does not exist yet.
     *
     * @return array<array-key, mixed>
     */
    public function read(string $path): array;

    /**
     * Write a nested array back to a translation file, creating the
     * containing directory if needed.
     *
     * @param  array<array-key, mixed>  $data
     */
    public function write(string $path, array $data): void;
}
