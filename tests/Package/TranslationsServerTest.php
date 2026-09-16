<?php

use colq2\Dolmetsch\Servers\TranslationsServer;
use colq2\Dolmetsch\Tools\AddTranslationTool;
use colq2\Dolmetsch\Tools\DeleteTranslationTool;
use colq2\Dolmetsch\Tools\GetTranslationTool;
use colq2\Dolmetsch\Tools\MoveTranslationTool;
use colq2\Dolmetsch\Tools\SearchTranslationTool;
use colq2\Dolmetsch\Tools\UpdateTranslationTool;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir().'/dolmetsch-translations-server-test-'.uniqid();
    $backendPath = "{$this->tempDir}/backend";
    $frontendPath = "{$this->tempDir}/frontend";

    mkdir("{$backendPath}/en_US", 0755, true);
    mkdir("{$backendPath}/de_DE", 0755, true);
    mkdir($frontendPath, 0755, true);

    file_put_contents("{$backendPath}/en_US/app.php", "<?php\n\nreturn [\n    'messages' => [\n        'created' => 'Created successfully.',\n        'updated' => 'Updated successfully.',\n    ],\n];\n");
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
});

afterEach(function (): void {
    File::deleteDirectory($this->tempDir);
});

it('adds a new translation', function (): void {
    TranslationsServer::tool(AddTranslationTool::class, [
        'domain' => 'backend',
        'path' => 'app.messages.deleted',
        'translations' => ['en_US' => 'Deleted successfully.', 'de_DE' => 'Erfolgreich gelöscht.'],
    ])->assertOk()->assertSee('app.messages.deleted');
});

it('rejects add-translation for an unknown domain', function (): void {
    TranslationsServer::tool(AddTranslationTool::class, [
        'domain' => 'unknown',
        'path' => 'app.messages.deleted',
        'translations' => ['en_US' => 'x'],
    ])->assertHasErrors(['Unknown domain. Valid domains: backend, frontend.']);
});

it('rejects add-translation when the path already exists', function (): void {
    TranslationsServer::tool(AddTranslationTool::class, [
        'domain' => 'backend',
        'path' => 'app.messages.created',
        'translations' => ['en_US' => 'x'],
    ])->assertHasErrors(['already exists']);
});

it('updates an existing translation', function (): void {
    TranslationsServer::tool(UpdateTranslationTool::class, [
        'domain' => 'backend',
        'path' => 'app.messages.created',
        'translations' => ['en_US' => 'Created!'],
    ])->assertOk()->assertSee('app.messages.created');
});

it('rejects update-translation when the path does not exist', function (): void {
    TranslationsServer::tool(UpdateTranslationTool::class, [
        'domain' => 'backend',
        'path' => 'app.messages.missing',
        'translations' => ['en_US' => 'x'],
    ])->assertHasErrors(['does not exist']);
});

it('moves a translation to a new path', function (): void {
    TranslationsServer::tool(MoveTranslationTool::class, [
        'domain' => 'frontend',
        'path' => 'common.actions.cancel',
        'newPath' => 'common.cancel_action',
    ])->assertOk()->assertSee('common.cancel_action');
});

it('deletes a leaf translation', function (): void {
    TranslationsServer::tool(DeleteTranslationTool::class, [
        'domain' => 'frontend',
        'path' => 'common.actions.cancel',
    ])->assertOk()->assertSee('common.actions.cancel');
});

it('rejects deleting a group without recursive, reporting the key count', function (): void {
    TranslationsServer::tool(DeleteTranslationTool::class, [
        'domain' => 'backend',
        'path' => 'app.messages',
    ])->assertHasErrors(['recursive: true']);
});

it('deletes a group when recursive is true', function (): void {
    TranslationsServer::tool(DeleteTranslationTool::class, [
        'domain' => 'backend',
        'path' => 'app.messages',
        'recursive' => true,
    ])->assertOk();
});

it('searches translations by text', function (): void {
    TranslationsServer::tool(SearchTranslationTool::class, [
        'query' => 'created',
    ])->assertOk()->assertSee('Created successfully.');
});

it('gets a leaf translation across locales', function (): void {
    TranslationsServer::tool(GetTranslationTool::class, [
        'domain' => 'backend',
        'path' => 'app.messages.created',
    ])->assertOk()->assertSee(['Created successfully.', 'Erfolgreich erstellt.']);
});

it('gets the immediate children of a group', function (): void {
    TranslationsServer::tool(GetTranslationTool::class, [
        'domain' => 'frontend',
        'path' => 'common.actions',
    ])->assertOk()->assertSee(['"key":"add"', '"key":"cancel"']);
});
