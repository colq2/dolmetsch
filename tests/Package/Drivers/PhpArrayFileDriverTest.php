<?php

use colq2\Dolmetsch\Drivers\PhpArrayFileDriver;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir().'/dolmetsch-php-driver-test-'.uniqid();
    mkdir($this->tempDir, 0755, true);
    $this->driver = new PhpArrayFileDriver;
});

afterEach(function (): void {
    File::deleteDirectory($this->tempDir);
});

it('returns an empty array when the file does not exist', function (): void {
    expect($this->driver->read("{$this->tempDir}/missing.php"))->toBe([]);
});

it('round-trips a nested array through read and write', function (): void {
    $path = "{$this->tempDir}/app.php";
    $data = [
        'messages' => [
            'created' => 'Created successfully.',
            'count' => 3,
            'enabled' => true,
            'nothing' => null,
        ],
    ];

    $this->driver->write($path, $data);

    expect($this->driver->read($path))->toBe($data);
});

it('escapes single quotes and backslashes in exported string values', function (): void {
    $path = "{$this->tempDir}/app.php";
    $data = ['key' => "It's a \\ backslash"];

    $this->driver->write($path, $data);

    expect($this->driver->read($path))->toBe($data);
});

it('creates the containing directory when it does not exist yet', function (): void {
    $path = "{$this->tempDir}/nested/dir/app.php";

    $this->driver->write($path, ['a' => 'b']);

    expect(is_file($path))->toBeTrue()
        ->and($this->driver->read($path))->toBe(['a' => 'b']);
});

it('exports string keys and values using double quotes', function (): void {
    $path = "{$this->tempDir}/app.php";

    $this->driver->write($path, [
        'messages' => [
            'created' => 'Created successfully.',
        ],
    ]);

    $contents = file_get_contents($path);

    expect($contents)
        ->toBe(<<<'PHP'
        <?php
        return [
            "messages" => [
                "created" => "Created successfully."
            ]
        ];

        PHP);
});

it('writes files directly without shelling out to a formatter', function (): void {
    $path = "{$this->tempDir}/app.php";

    $this->driver->write($path, ['key' => 'value']);

    expect(file_get_contents($path))->not->toContain("'key'")
        ->and(file_get_contents($path))->not->toStartWith("<?php\n\n");
});
