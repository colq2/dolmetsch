<?php

use colq2\Dolmetsch\TranslationManager;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir().'/dolmetsch-translation-manager-md-test-'.uniqid();
    $backendPath = "{$this->tempDir}/backend";
    $frontendPath = "{$this->tempDir}/frontend";

    mkdir("{$backendPath}/en_US", 0755, true);
    mkdir("{$backendPath}/de_DE", 0755, true);
    mkdir($frontendPath, 0755, true);

    // de_DE intentionally only has "messages.created" (no "title", no "updated")
    // so it exercises the skip-missing-locale and prune-to-empty-file paths.
    file_put_contents("{$backendPath}/en_US/app.php", "<?php\n\nreturn [\n    'messages' => [\n        'created' => 'Created successfully.',\n        'updated' => 'Updated successfully.',\n    ],\n    'title' => 'App',\n];\n");
    file_put_contents("{$backendPath}/de_DE/app.php", "<?php\n\nreturn [\n    'messages' => [\n        'created' => 'Erfolgreich erstellt.',\n    ],\n];\n");

    file_put_contents("{$frontendPath}/en-US.json", json_encode([
        'common' => ['actions' => ['add' => 'Add', 'cancel' => 'Cancel']],
    ], JSON_PRETTY_PRINT));
    file_put_contents("{$frontendPath}/de-DE.json", json_encode([
        'common' => ['actions' => ['add' => 'Hinzufügen']],
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

it('moves a key within the same domain to a different group, skipping locales that lack it', function (): void {
    $result = $this->manager->move('backend', 'app.title', 'actions.app_title');

    expect($result)->toBe([
        'domain' => 'backend',
        'path' => 'app.title',
        'newDomain' => 'backend',
        'newPath' => 'actions.app_title',
        'locales' => ['en_US'],
    ])
        ->and($this->manager->get('backend', 'actions.app_title')['values'])->toBe(['en_US' => 'App'])
        ->and($this->manager->exists('backend', 'app.title'))->toBeFalse()
        ->and($this->manager->get('backend', 'app.messages')['total'])->toBe(2);
});

it('moves a key across domains, pruning a now-empty parent in one locale while leaving a sibling intact in another', function (): void {
    $result = $this->manager->move('backend', 'app.messages.created', 'notes.created', newDomain: 'frontend');

    expect($result['locales'])->toBe(['en_US', 'de_DE'])
        ->and($this->manager->get('frontend', 'notes.created')['values'])->toBe([
            'en_US' => 'Created successfully.',
            'de_DE' => 'Erfolgreich erstellt.',
        ])
        ->and($this->manager->get('backend', 'app.messages')['items'])->toBe([
            ['key' => 'updated', 'path' => 'app.messages.updated', 'is_group' => false, 'value' => 'Updated successfully.'],
        ]);

    // de_DE's "messages" group had no other keys, so it was pruned away entirely.
    config()->set('dolmetsch.main_locale', 'de_DE');
    expect(fn () => $this->manager->get('backend', 'app.messages'))
        ->toThrow(InvalidArgumentException::class, "Path 'app.messages' was not found in domain 'backend'.");
});

it('deletes a leaf, leaving its siblings intact', function (): void {
    $result = $this->manager->delete('frontend', 'common.actions.cancel');

    expect($result)->toBe(['domain' => 'frontend', 'path' => 'common.actions.cancel', 'locales' => ['en_US']])
        ->and($this->manager->get('frontend', 'common.actions')['items'])->toBe([
            ['key' => 'add', 'path' => 'common.actions.add', 'is_group' => false, 'value' => 'Add'],
        ]);
});

it('deletes a leaf and prunes the now fully-emptied parent for that locale', function (): void {
    $this->manager->delete('backend', 'app.messages.created');

    expect($this->manager->get('backend', 'app.messages')['items'])->toBe([
        ['key' => 'updated', 'path' => 'app.messages.updated', 'is_group' => false, 'value' => 'Updated successfully.'],
    ]);

    config()->set('dolmetsch.main_locale', 'de_DE');
    expect(fn () => $this->manager->get('backend', 'app.messages'))
        ->toThrow(InvalidArgumentException::class, "Path 'app.messages' was not found in domain 'backend'.");
});

it('throws when deleting a group without recursive, reporting the key count', function (): void {
    expect(fn () => $this->manager->delete('backend', 'app.messages'))
        ->toThrow(InvalidArgumentException::class, "Path 'app.messages' is a group containing 2 key(s). Pass recursive: true to delete it and all its children.")
        ->and($this->manager->exists('backend', 'app.messages.created'))->toBeTrue();
});

it('deletes a group recursively when recursive is true', function (): void {
    $result = $this->manager->delete('backend', 'app.messages', recursive: true);

    expect($result['locales'])->toBe(['en_US', 'de_DE'])
        ->and($this->manager->exists('backend', 'app.messages'))->toBeFalse()
        ->and($this->manager->exists('backend', 'app.title'))->toBeTrue();
});
