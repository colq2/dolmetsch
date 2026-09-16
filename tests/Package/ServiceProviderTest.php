<?php

use colq2\Dolmetsch\Servers\TranslationsServer;
use colq2\Dolmetsch\TranslationManager;
use Laravel\Mcp\Facades\Mcp;

it('merges the packaged default config', function (): void {
    expect(config('dolmetsch.handle'))->toBe('translator')
        ->and(config('dolmetsch.main_locale'))->toBe('en')
        ->and(config('dolmetsch.domains.backend.driver'))->toBe('php')
        ->and(config('dolmetsch.domains.backend.locales'))->toHaveKey('en');
});

it('resolves the translation manager as a singleton', function (): void {
    expect(app(TranslationManager::class))
        ->toBeInstanceOf(TranslationManager::class)
        ->toBe(app(TranslationManager::class));
});

it('does not register the mcp server outside the configured environments', function (): void {
    // Testbench boots as "testing", the default gate only allows "local".
    expect(Mcp::getLocalServer('translator'))->toBeNull();
});

it('exposes every tool on the server', function (): void {
    $tools = (new ReflectionClass(TranslationsServer::class))
        ->getDefaultProperties()['tools'];

    expect($tools)->toHaveCount(6);
});
