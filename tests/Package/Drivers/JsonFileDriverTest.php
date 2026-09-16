<?php

use colq2\Dolmetsch\Drivers\JsonFileDriver;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir().'/dolmetsch-json-driver-test-'.uniqid();
    mkdir($this->tempDir, 0755, true);
    $this->driver = new JsonFileDriver;
});

afterEach(function (): void {
    File::deleteDirectory($this->tempDir);
});

it('returns an empty array when the file does not exist', function (): void {
    expect($this->driver->read("{$this->tempDir}/missing.json"))->toBe([]);
});

it('round-trips a nested array through read and write', function (): void {
    $path = "{$this->tempDir}/en-US.json";
    $data = [
        'common' => [
            'actions' => [
                'add' => 'Add',
                'cancel' => 'Cancel',
            ],
        ],
    ];

    $this->driver->write($path, $data);

    expect($this->driver->read($path))->toBe($data);
});

it('writes JSON with 2-space indentation, unescaped unicode and slashes', function (): void {
    $path = "{$this->tempDir}/en-US.json";

    $this->driver->write($path, [
        'common' => [
            'title' => 'Waste free waters — world’s oceans',
            'path' => 'a/b',
        ],
    ]);

    $contents = file_get_contents($path);

    expect($contents)
        ->toBe(<<<'JSON'
        {
          "common": {
            "title": "Waste free waters — world’s oceans",
            "path": "a/b"
          }
        }

        JSON);
});

it('creates the containing directory when it does not exist yet', function (): void {
    $path = "{$this->tempDir}/nested/dir/en-US.json";

    $this->driver->write($path, ['a' => 'b']);

    expect(is_file($path))->toBeTrue()
        ->and($this->driver->read($path))->toBe(['a' => 'b']);
});

it('preserves existing key order and appends new keys at their natural nesting point', function (): void {
    $path = "{$this->tempDir}/en-US.json";

    $this->driver->write($path, ['b' => '2', 'a' => '1']);

    $data = $this->driver->read($path);
    $data['common']['new_key'] = 'New Value';

    $this->driver->write($path, $data);

    expect(array_keys($this->driver->read($path)))->toBe(['b', 'a', 'common'])
        ->and($this->driver->read($path)['common'])->toBe(['new_key' => 'New Value']);
});
