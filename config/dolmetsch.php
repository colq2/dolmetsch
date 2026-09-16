<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MCP Server Handle
    |--------------------------------------------------------------------------
    |
    | The handle the MCP server is registered under. This is the name your
    | client refers to, e.g. `php artisan mcp:start translator`.
    |
    */

    'handle' => env('DOLMETSCH_HANDLE', 'translator'),

    /*
    |--------------------------------------------------------------------------
    | Server Registration
    |--------------------------------------------------------------------------
    |
    | The tools write directly to your repository's translation files, so the
    | server only registers in the listed environments. Set to true or false
    | to force it on or off regardless of environment.
    |
    */

    'register_local_server' => ['local'],

    /*
    |--------------------------------------------------------------------------
    | Main Locale
    |--------------------------------------------------------------------------
    |
    | The source-of-truth locale that search/list/get operations scan when
    | they only need to look at one file per domain (structure is assumed
    | to mirror across locales). Must be a key in every domain's `locales`.
    |
    */

    'main_locale' => env('DOLMETSCH_MAIN_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Domains
    |--------------------------------------------------------------------------
    |
    | Each domain describes one area of translation storage: which driver
    | reads/writes its files, where those files live, and how the logical
    | locale codes map to the actual filename/dirname that domain uses.
    |
    | driver: 'php'  => lang/{locale}/{group}.php, paths are "group.key.sub"
    |         'json' => lang/{locale}.json,        paths are "key.sub"
    |
    | locales: logical code => the file or directory name on disk, letting
    | you keep e.g. 'de_DE' in code while the folder is named 'de-DE'.
    |
    */

    'domains' => [

        'backend' => [
            'driver' => 'php',
            'path' => lang_path(),
            'locales' => [
                'en' => 'en',
            ],
        ],

        // 'frontend' => [
        //     'driver' => 'json',
        //     'path' => resource_path('js/Lang'),
        //     'locales' => [
        //         'en' => 'en',
        //     ],
        // ],

    ],

];
