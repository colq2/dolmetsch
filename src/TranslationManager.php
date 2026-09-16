<?php

namespace colq2\Dolmetsch;

use colq2\Dolmetsch\Drivers\JsonFileDriver;
use colq2\Dolmetsch\Drivers\PhpArrayFileDriver;
use colq2\Dolmetsch\Drivers\TranslationFileDriver;
use Generator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TranslationManager
{
    /**
     * @param  array<string, string>  $translations  locale => text, must include the configured main_locale
     * @return array{domain: string, path: string, locales: array<int, string>}
     */
    public function add(string $domain, string $path, array $translations): array
    {
        $mainLocale = config('dolmetsch.main_locale');

        if (! array_key_exists($mainLocale, $translations)) {
            throw new InvalidArgumentException("The 'translations' payload must include the main locale '{$mainLocale}'.");
        }

        if ($this->exists($domain, $path)) {
            throw new InvalidArgumentException("Path '{$path}' already exists in domain '{$domain}'. Use update instead.");
        }

        return $this->writeToLocales($domain, $path, $translations);
    }

    /**
     * @param  array<string, string>  $translations  locale => text, at least one locale required
     * @return array{domain: string, path: string, locales: array<int, string>}
     */
    public function update(string $domain, string $path, array $translations): array
    {
        if ($translations === []) {
            throw new InvalidArgumentException('At least one locale must be provided.');
        }

        if (! $this->exists($domain, $path)) {
            throw new InvalidArgumentException("Path '{$path}' does not exist in domain '{$domain}'. Use add instead.");
        }

        return $this->writeToLocales($domain, $path, $translations);
    }

    /**
     * @return array{domain: string, path: string, is_group: false, values: array<string, mixed>}|array{domain: string, path: string, is_group: true, items: array<int, array{key: string, path: string, is_group: bool, value: mixed}>, total: int, page: int, limit: int, has_more: bool}
     */
    public function get(string $domain, string $path, int $limit = 20, int $page = 1): array
    {
        $domainConfig = $this->domainConfig($domain);
        $mainLocale = config('dolmetsch.main_locale');
        [$group, $segments] = $this->splitPath($domainConfig['driver'], $path);
        $key = implode('.', $segments);

        $driver = $this->driver($domainConfig['driver']);
        $mainLocaleFile = $this->localeFile($domainConfig, $domain, $mainLocale);
        $mainData = $driver->read($this->filePath($domainConfig, $mainLocaleFile, $group));

        if ($key !== '' && ! Arr::has($mainData, $key)) {
            throw new InvalidArgumentException("Path '{$path}' was not found in domain '{$domain}'.");
        }

        $node = $key === '' ? $mainData : Arr::get($mainData, $key);

        if (! is_array($node)) {
            return [
                'domain' => $domain,
                'path' => $path,
                'is_group' => false,
                'values' => $this->valuesAcrossLocales($domainConfig, $domain, $driver, $group, $key),
            ];
        }

        return array_merge(
            ['domain' => $domain, 'path' => $path, 'is_group' => true],
            $this->paginateChildren($node, $path, $limit, $page),
        );
    }

    /**
     * @return array{items: array<int, array{domain: string, path: string, value: mixed}>, total: int, page: int, limit: int, has_more: bool}
     */
    public function search(string $query, string $by = 'text', ?string $domain = null, int $limit = 20, int $page = 1): array
    {
        if (! in_array($by, ['text', 'key'], true)) {
            throw new InvalidArgumentException("The 'by' parameter must be 'text' or 'key'.");
        }

        $domains = $domain !== null ? [$domain] : array_keys(config('dolmetsch.domains', []));
        $mainLocale = config('dolmetsch.main_locale');
        $needle = Str::lower($query);

        $results = [];

        foreach ($domains as $domainName) {
            $domainConfig = $this->domainConfig($domainName);
            $driver = $this->driver($domainConfig['driver']);
            $mainLocaleFile = $this->localeFile($domainConfig, $domainName, $mainLocale);

            foreach ($this->mainLocaleFiles($domainConfig, $mainLocaleFile) as ['group' => $group, 'path' => $filePath]) {
                $data = $driver->read($filePath);

                foreach ($this->flatten($data) as $key => $value) {
                    $fullPath = $group !== null ? "{$group}.{$key}" : $key;
                    $haystack = $by === 'text' ? (string) $value : $fullPath;

                    if (Str::contains(Str::lower($haystack), $needle)) {
                        $results[] = ['domain' => $domainName, 'path' => $fullPath, 'value' => $value];
                    }
                }
            }
        }

        return $this->paginate($results, $limit, $page);
    }

    /**
     * Copies the value for every locale that currently has $path to $newPath
     * (in $newDomain if given, else the same domain), deletes it from the
     * source, and prunes now-empty parent arrays. Locales missing the key
     * are skipped, not errored.
     *
     * @return array{domain: string, path: string, newDomain: string, newPath: string, locales: array<int, string>}
     */
    public function move(string $domain, string $path, string $newPath, ?string $newDomain = null): array
    {
        $targetDomain = $newDomain ?? $domain;

        $sourceDomainConfig = $this->domainConfig($domain);
        $targetDomainConfig = $this->domainConfig($targetDomain);

        [$sourceGroup, $sourceSegments] = $this->splitPath($sourceDomainConfig['driver'], $path);
        [$targetGroup, $targetSegments] = $this->splitPath($targetDomainConfig['driver'], $newPath);

        if ($sourceSegments === []) {
            throw new InvalidArgumentException("Path '{$path}' must include a key, not just a group name.");
        }

        if ($targetSegments === []) {
            throw new InvalidArgumentException("Path '{$newPath}' must include a key, not just a group name.");
        }

        $sourceKey = implode('.', $sourceSegments);
        $targetKey = implode('.', $targetSegments);

        $sourceDriver = $this->driver($sourceDomainConfig['driver']);
        $targetDriver = $this->driver($targetDomainConfig['driver']);

        $movedLocales = [];

        foreach (array_keys($sourceDomainConfig['locales']) as $locale) {
            if (! array_key_exists($locale, $targetDomainConfig['locales'])) {
                continue;
            }

            $sourceFilePath = $this->filePath($sourceDomainConfig, $sourceDomainConfig['locales'][$locale], $sourceGroup);
            $targetFilePath = $this->filePath($targetDomainConfig, $targetDomainConfig['locales'][$locale], $targetGroup);

            if ($sourceFilePath === $targetFilePath) {
                $data = $sourceDriver->read($sourceFilePath);

                if (! Arr::has($data, $sourceKey)) {
                    continue;
                }

                $value = Arr::get($data, $sourceKey);
                Arr::forget($data, $sourceKey);
                $this->pruneEmptyParents($data, $sourceSegments);
                Arr::set($data, $targetKey, $value);

                $sourceDriver->write($sourceFilePath, $data);
            } else {
                $sourceData = $sourceDriver->read($sourceFilePath);

                if (! Arr::has($sourceData, $sourceKey)) {
                    continue;
                }

                $value = Arr::get($sourceData, $sourceKey);
                Arr::forget($sourceData, $sourceKey);
                $this->pruneEmptyParents($sourceData, $sourceSegments);
                $sourceDriver->write($sourceFilePath, $sourceData);

                $targetData = $targetDriver->read($targetFilePath);
                Arr::set($targetData, $targetKey, $value);
                $targetDriver->write($targetFilePath, $targetData);
            }

            $movedLocales[] = $locale;
        }

        return ['domain' => $domain, 'path' => $path, 'newDomain' => $targetDomain, 'newPath' => $newPath, 'locales' => $movedLocales];
    }

    /**
     * Deletes a leaf by default. Deleting a non-leaf (group) requires
     * $recursive to be true, otherwise throws reporting how many keys
     * would be deleted. Prunes now-empty parent arrays afterward.
     *
     * @return array{domain: string, path: string, locales: array<int, string>}
     */
    public function delete(string $domain, string $path, bool $recursive = false): array
    {
        $domainConfig = $this->domainConfig($domain);
        [$group, $segments] = $this->splitPath($domainConfig['driver'], $path);

        if ($segments === []) {
            throw new InvalidArgumentException("Path '{$path}' must include a key, not just a group name.");
        }

        $key = implode('.', $segments);
        $driver = $this->driver($domainConfig['driver']);
        $mainLocale = config('dolmetsch.main_locale');
        $mainLocaleFile = $this->localeFile($domainConfig, $domain, $mainLocale);
        $mainData = $driver->read($this->filePath($domainConfig, $mainLocaleFile, $group));

        if (! Arr::has($mainData, $key)) {
            throw new InvalidArgumentException("Path '{$path}' was not found in domain '{$domain}'.");
        }

        $node = Arr::get($mainData, $key);

        if (is_array($node) && ! $recursive) {
            $count = $this->countLeaves($node);

            throw new InvalidArgumentException("Path '{$path}' is a group containing {$count} key(s). Pass recursive: true to delete it and all its children.");
        }

        $deletedLocales = [];

        foreach (array_keys($domainConfig['locales']) as $locale) {
            $localeFile = $this->localeFile($domainConfig, $domain, $locale);
            $filePath = $this->filePath($domainConfig, $localeFile, $group);
            $data = $driver->read($filePath);

            if (! Arr::has($data, $key)) {
                continue;
            }

            Arr::forget($data, $key);
            $this->pruneEmptyParents($data, $segments);
            $driver->write($filePath, $data);

            $deletedLocales[] = $locale;
        }

        return ['domain' => $domain, 'path' => $path, 'locales' => $deletedLocales];
    }

    public function exists(string $domain, string $path): bool
    {
        $domainConfig = $this->domainConfig($domain);
        $mainLocale = config('dolmetsch.main_locale');
        [$group, $segments] = $this->splitPath($domainConfig['driver'], $path);

        $driver = $this->driver($domainConfig['driver']);
        $mainLocaleFile = $this->localeFile($domainConfig, $domain, $mainLocale);
        $data = $driver->read($this->filePath($domainConfig, $mainLocaleFile, $group));

        return Arr::has($data, implode('.', $segments));
    }

    /**
     * @param  array<string, string>  $translations
     * @return array{domain: string, path: string, locales: array<int, string>}
     */
    protected function writeToLocales(string $domain, string $path, array $translations): array
    {
        $domainConfig = $this->domainConfig($domain);
        $driver = $this->driver($domainConfig['driver']);
        [$group, $segments] = $this->splitPath($domainConfig['driver'], $path);

        if ($segments === []) {
            throw new InvalidArgumentException("Path '{$path}' must include a key, not just a group name.");
        }

        $key = implode('.', $segments);
        $written = [];

        foreach ($translations as $locale => $text) {
            $localeFile = $this->localeFile($domainConfig, $domain, $locale);
            $filePath = $this->filePath($domainConfig, $localeFile, $group);

            $data = $driver->read($filePath);
            Arr::set($data, $key, $text);
            $driver->write($filePath, $data);

            $written[] = $locale;
        }

        return ['domain' => $domain, 'path' => $path, 'locales' => $written];
    }

    /**
     * @return array<string, mixed>
     */
    protected function valuesAcrossLocales(array $domainConfig, string $domain, TranslationFileDriver $driver, ?string $group, string $key): array
    {
        $values = [];

        foreach (array_keys($domainConfig['locales']) as $locale) {
            $localeFile = $this->localeFile($domainConfig, $domain, $locale);
            $data = $driver->read($this->filePath($domainConfig, $localeFile, $group));

            if ($key === '' ? $data !== [] : Arr::has($data, $key)) {
                $values[$locale] = $key === '' ? $data : Arr::get($data, $key);
            }
        }

        return $values;
    }

    /**
     * @param  array<array-key, mixed>  $node
     * @return array{items: array<int, array{key: string, path: string, is_group: bool, value: mixed}>, total: int, page: int, limit: int, has_more: bool}
     */
    protected function paginateChildren(array $node, string $parentKey, int $limit, int $page): array
    {
        $children = [];

        foreach ($node as $childKey => $childValue) {
            $children[] = [
                'key' => (string) $childKey,
                'path' => $parentKey === '' ? (string) $childKey : "{$parentKey}.{$childKey}",
                'is_group' => is_array($childValue),
                'value' => is_array($childValue) ? null : $childValue,
            ];
        }

        return $this->paginate($children, $limit, $page);
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array{items: array<int, mixed>, total: int, page: int, limit: int, has_more: bool}
     */
    protected function paginate(array $items, int $limit, int $page): array
    {
        $limit = max(1, min($limit, 100));
        $page = max(1, $page);
        $offset = ($page - 1) * $limit;
        $total = count($items);

        return [
            'items' => array_slice($items, $offset, $limit),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'has_more' => $offset + $limit < $total,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function domainConfig(string $domain): array
    {
        $domains = config('dolmetsch.domains', []);

        if (! array_key_exists($domain, $domains)) {
            throw new InvalidArgumentException("Unknown domain '{$domain}'. Valid domains: ".implode(', ', array_keys($domains)).'.');
        }

        return $domains[$domain];
    }

    protected function localeFile(array $domainConfig, string $domain, string $locale): string
    {
        if (! array_key_exists($locale, $domainConfig['locales'])) {
            throw new InvalidArgumentException("Unknown locale '{$locale}' for domain '{$domain}'.");
        }

        return $domainConfig['locales'][$locale];
    }

    protected function driver(string $type): TranslationFileDriver
    {
        return match ($type) {
            'php' => new PhpArrayFileDriver,
            'json' => new JsonFileDriver,
            default => throw new InvalidArgumentException("Unknown translation driver '{$type}'."),
        };
    }

    protected function filePath(array $domainConfig, string $localeFile, ?string $group): string
    {
        $basePath = rtrim($domainConfig['path'], '/');

        return $group !== null
            ? "{$basePath}/{$localeFile}/{$group}.php"
            : "{$basePath}/{$localeFile}.json";
    }

    /**
     * @return array{0: ?string, 1: array<int, string>}
     */
    protected function splitPath(string $driverType, string $path): array
    {
        if (trim($path) === '') {
            throw new InvalidArgumentException('Path must not be empty.');
        }

        $segments = explode('.', $path);

        if ($driverType === 'php') {
            $group = array_shift($segments);

            return [$group, $segments];
        }

        return [null, $segments];
    }

    /**
     * @return array<int, array{group: ?string, path: string}> scoped to the main locale
     */
    protected function mainLocaleFiles(array $domainConfig, string $mainLocaleFile): array
    {
        $basePath = rtrim($domainConfig['path'], '/');

        if ($domainConfig['driver'] === 'php') {
            $files = [];

            foreach (glob("{$basePath}/{$mainLocaleFile}/*.php") ?: [] as $file) {
                $files[] = ['group' => pathinfo($file, PATHINFO_FILENAME), 'path' => $file];
            }

            return $files;
        }

        return [['group' => null, 'path' => "{$basePath}/{$mainLocaleFile}.json"]];
    }

    /**
     * @param  array<array-key, mixed>  $node
     */
    protected function countLeaves(array $node): int
    {
        $count = 0;

        foreach ($node as $value) {
            $count += is_array($value) ? $this->countLeaves($value) : 1;
        }

        return $count;
    }

    /**
     * Removes now-empty ancestor arrays after a leaf at $segments was
     * deleted from $data, walking from the deepest parent up to the root.
     *
     * @param  array<array-key, mixed>  $data
     * @param  array<int, string>  $segments  the deleted leaf's full path segments, including the leaf itself
     */
    protected function pruneEmptyParents(array &$data, array $segments): void
    {
        array_pop($segments);

        while ($segments !== []) {
            $parentPath = implode('.', $segments);
            $parent = Arr::get($data, $parentPath);

            if (! is_array($parent) || $parent !== []) {
                break;
            }

            Arr::forget($data, $parentPath);
            array_pop($segments);
        }
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return Generator<string, mixed>
     */
    protected function flatten(array $data, string $prefix = ''): Generator
    {
        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                yield from $this->flatten($value, $path);
            } else {
                yield $path => $value;
            }
        }
    }
}
