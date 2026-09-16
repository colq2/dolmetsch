<?php

use colq2\Dolmetsch\TranslationManager;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir().'/dolmetsch-translation-manager-test-'.uniqid();
    $backendPath = "{$this->tempDir}/backend";
    $frontendPath = "{$this->tempDir}/frontend";

    mkdir("{$backendPath}/en_US", 0755, true);
    mkdir("{$backendPath}/de_DE", 0755, true);
    mkdir($frontendPath, 0755, true);

    file_put_contents("{$backendPath}/en_US/app.php", "<?php\n\nreturn [\n    'messages' => [\n        'created' => 'Created successfully.',\n        'updated' => 'Updated successfully.',\n    ],\n    'title' => 'App',\n];\n");
    file_put_contents("{$backendPath}/de_DE/app.php", "<?php\n\nreturn [\n    'messages' => [\n        'created' => 'Erfolgreich erstellt.',\n    ],\n];\n");

    file_put_contents("{$frontendPath}/en-US.json", json_encode([
        'common' => [
            'actions' => ['add' => 'Add', 'cancel' => 'Cancel'],
        ],
    ], JSON_PRETTY_PRINT));
    file_put_contents("{$frontendPath}/de-DE.json", json_encode([
        'common' => [
            'actions' => ['add' => 'Hinzufügen'],
        ],
    ], JSON_PRETTY_PRINT));

    config()->set('dolmetsch', [
        'main_locale' => 'en_US',
        'domains' => [
            'backend' => [
                'driver' => 'php',
                'path' => $backendPath,
                'locales' => ['en_US' => 'en_US', 'de_DE' => 'de_DE'],
            ],
            'frontend' => [
                'driver' => 'json',
                'path' => $frontendPath,
                'locales' => ['en_US' => 'en-US', 'de_DE' => 'de-DE'],
            ],
        ],
    ]);

    $this->manager = new TranslationManager;
});

afterEach(function (): void {
    File::deleteDirectory($this->tempDir);
});

it('adds a new translation across the given locales', function (): void {
    $result = $this->manager->add('backend', 'app.messages.deleted', [
        'en_US' => 'Deleted successfully.',
        'de_DE' => 'Erfolgreich gelöscht.',
    ]);

    expect($result)->toBe(['domain' => 'backend', 'path' => 'app.messages.deleted', 'locales' => ['en_US', 'de_DE']])
        ->and($this->manager->exists('backend', 'app.messages.deleted'))->toBeTrue();
});

it('throws when adding a path that already exists', function (): void {
    expect(fn () => $this->manager->add('backend', 'app.messages.created', ['en_US' => 'x']))
        ->toThrow(InvalidArgumentException::class, "Path 'app.messages.created' already exists in domain 'backend'. Use update instead.");
});

it('throws when adding without the main locale', function (): void {
    expect(fn () => $this->manager->add('backend', 'app.messages.deleted', ['de_DE' => 'x']))
        ->toThrow(InvalidArgumentException::class, "The 'translations' payload must include the main locale 'en_US'.");
});

it('updates an existing translation', function (): void {
    $result = $this->manager->update('backend', 'app.messages.created', ['en_US' => 'Created!']);

    expect($result)->toBe(['domain' => 'backend', 'path' => 'app.messages.created', 'locales' => ['en_US']])
        ->and($this->manager->get('backend', 'app.messages.created')['values']['en_US'])->toBe('Created!');
});

it('throws when updating a path that does not exist', function (): void {
    expect(fn () => $this->manager->update('backend', 'app.messages.missing', ['en_US' => 'x']))
        ->toThrow(InvalidArgumentException::class, "Path 'app.messages.missing' does not exist in domain 'backend'. Use add instead.");
});

it('throws when updating with no locales', function (): void {
    expect(fn () => $this->manager->update('backend', 'app.messages.created', []))
        ->toThrow(InvalidArgumentException::class, 'At least one locale must be provided.');
});

it('gets a leaf value across every locale that has it', function (): void {
    $result = $this->manager->get('backend', 'app.messages.created');

    expect($result)->toBe([
        'domain' => 'backend',
        'path' => 'app.messages.created',
        'is_group' => false,
        'values' => ['en_US' => 'Created successfully.', 'de_DE' => 'Erfolgreich erstellt.'],
    ]);
});

it('only includes locales that actually have the leaf value', function (): void {
    $result = $this->manager->get('backend', 'app.messages.updated');

    expect($result['values'])->toBe(['en_US' => 'Updated successfully.']);
});

it('gets the immediate children of a group, one level deep', function (): void {
    $result = $this->manager->get('backend', 'app');

    expect($result['is_group'])->toBeTrue()
        ->and($result['items'])->toBe([
            ['key' => 'messages', 'path' => 'app.messages', 'is_group' => true, 'value' => null],
            ['key' => 'title', 'path' => 'app.title', 'is_group' => false, 'value' => 'App'],
        ])
        ->and($result['total'])->toBe(2);
});

it('paginates group children at the boundaries', function (): void {
    $result = $this->manager->get('frontend', 'common.actions', limit: 1, page: 1);

    expect($result['items'])->toHaveCount(1)
        ->and($result['items'][0]['key'])->toBe('add')
        ->and($result['total'])->toBe(2)
        ->and($result['has_more'])->toBeTrue();

    $secondPage = $this->manager->get('frontend', 'common.actions', limit: 1, page: 2);

    expect($secondPage['items'])->toHaveCount(1)
        ->and($secondPage['items'][0]['key'])->toBe('cancel')
        ->and($secondPage['has_more'])->toBeFalse();
});

it('throws when getting a path that does not exist', function (): void {
    expect(fn () => $this->manager->get('backend', 'app.nope'))
        ->toThrow(InvalidArgumentException::class, "Path 'app.nope' was not found in domain 'backend'.");
});

it('searches by text in the main locale only', function (): void {
    $result = $this->manager->search('created');

    expect($result['items'])->toBe([
        ['domain' => 'backend', 'path' => 'app.messages.created', 'value' => 'Created successfully.'],
    ])
        ->and($result['total'])->toBe(1);
});

it('searches by key across all domains when domain is omitted', function (): void {
    $result = $this->manager->search('actions', by: 'key');

    expect($result['items'])->toBe([
        ['domain' => 'frontend', 'path' => 'common.actions.add', 'value' => 'Add'],
        ['domain' => 'frontend', 'path' => 'common.actions.cancel', 'value' => 'Cancel'],
    ]);
});

it('scopes search to a single domain when given', function (): void {
    $result = $this->manager->search('a', domain: 'backend');

    expect($result['items'])->not->toBeEmpty()
        ->and(collect($result['items'])->pluck('domain')->unique()->all())->toBe(['backend']);
});

it('paginates search results', function (): void {
    $first = $this->manager->search('e', limit: 1, page: 1);
    $second = $this->manager->search('e', limit: 1, page: 2);

    expect($first['items'])->toHaveCount(1)
        ->and($first['has_more'])->toBeTrue()
        ->and($second['items'])->toHaveCount(1)
        ->and($first['items'])->not->toBe($second['items']);
});

it('rejects an invalid search "by" value', function (): void {
    expect(fn () => $this->manager->search('x', by: 'nope'))
        ->toThrow(InvalidArgumentException::class, "The 'by' parameter must be 'text' or 'key'.");
});

it('throws a clear error for an unknown domain', function (): void {
    expect(fn () => $this->manager->get('unknown', 'x'))
        ->toThrow(InvalidArgumentException::class, "Unknown domain 'unknown'. Valid domains: backend, frontend.");
});
