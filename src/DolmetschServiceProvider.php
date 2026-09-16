<?php

namespace colq2\Dolmetsch;

use colq2\Dolmetsch\Servers\TranslationsServer;
use Illuminate\Support\Arr;
use Laravel\Mcp\Facades\Mcp;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class DolmetschServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('dolmetsch')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(TranslationManager::class);
    }

    /**
     * Registers the MCP server under the configured handle. Disabled by
     * default outside local, since the tools write to the repository's
     * translation files.
     */
    public function packageBooted(): void
    {
        if (! $this->shouldRegisterServer()) {
            return;
        }

        Mcp::local(config('dolmetsch.handle', 'translator'), TranslationsServer::class);
    }

    protected function shouldRegisterServer(): bool
    {
        $register = config('dolmetsch.register_local_server', 'local');

        if (is_bool($register)) {
            return $register;
        }

        return $this->app->environment(Arr::wrap($register));
    }
}
