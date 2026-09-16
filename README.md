# Dolmetsch

[![Latest Version on Packagist](https://img.shields.io/packagist/v/colq2/dolmetsch.svg?style=flat-square)](https://packagist.org/packages/colq2/dolmetsch)
[![Total Downloads](https://img.shields.io/packagist/dt/colq2/dolmetsch.svg?style=flat-square)](https://packagist.org/packages/colq2/dolmetsch)

An MCP server for managing translation files in Laravel applications.

Editing `lang/` by hand — or letting a coding agent edit it — drifts. A key lands in `de_DE` but not `en_US`, a PHP array and its JSON counterpart fall out of sync, a rename leaves an orphaned parent group behind. Dolmetsch gives an AI agent six deterministic tools that read and write those files structurally, so the structure cannot drift.

> *Dolmetsch* is an old German word for an interpreter — someone who carries meaning between languages.

## Installation

```bash
composer require colq2/dolmetsch
```

Publish the config:

```bash
php artisan vendor:publish --tag="dolmetsch-config"
```

## Configuration

A **domain** is one area of translation storage. Each names a driver, a base path, and the locales it holds:

```php
'main_locale' => 'de_DE',

'domains' => [
    'backend' => [
        'driver' => 'php',            // lang/{locale}/{group}.php
        'path' => lang_path(),
        'locales' => [
            'de_DE' => 'de-DE',       // logical code => name on disk
            'en_US' => 'en-US',
        ],
    ],

    'frontend' => [
        'driver' => 'json',           // {path}/{locale}.json
        'path' => resource_path('js/Lang'),
        'locales' => [
            'de_DE' => 'de-DE',
            'en_US' => 'en-US',
        ],
    ],
],
```

Two drivers ship:

| Driver | Layout | Path syntax |
|---|---|---|
| `php` | `{path}/{locale}/{group}.php` | `group.key.subkey` — the first segment is the file |
| `json` | `{path}/{locale}.json` | `key.subkey` |

The `locales` map lets the code you write (`de_DE`) differ from the directory on disk (`de-DE`).

`main_locale` is the source of truth. Search and group listings scan only that locale, assuming structure mirrors across the rest — which is exactly the invariant these tools maintain.

### Registration

The server registers under the `dolmetsch.handle` name (default `translator`) in the environments listed in `register_local_server`, which defaults to `['local']`. **The tools write to files in your repository**, so keep this off in production. Set it to `true` or `false` to override the environment gate entirely.

## Usage

Register the MCP server with your client:

```bash
claude mcp add translator -- php artisan mcp:start translator
```

Six tools become available:

| Tool | What it does |
|---|---|
| `add-translation` | Adds a new key across locales. Requires the main locale; fails if the path exists. |
| `update-translation` | Updates an existing key. Fails if the path does not exist. |
| `get-translation` | Reads a path. A leaf returns every locale's value; a group returns its immediate children, paginated. |
| `search-translation` | Substring search by value text or by key path, across one domain or all. |
| `move-translation` | Renames or relocates a key, across domains if asked, pruning emptied parent groups. |
| `delete-translation` | Deletes a leaf. Deleting a group needs `recursive: true` and reports the key count first. |

The split between `add` and `update` is deliberate: an agent that means to create a key should not silently overwrite one, and vice versa.

You can also use the manager directly:

```php
use colq2\Dolmetsch\TranslationManager;

app(TranslationManager::class)->add('backend', 'app.messages.saved', [
    'de_DE' => 'Gespeichert.',
    'en_US' => 'Saved.',
]);
```

## Behaviour worth knowing

- **Locales are not invented.** Writes touch only the locales you pass. Moves and deletes skip locales that lack the key rather than erroring.
- **Empty parents are pruned.** Removing the last child of a group removes the group.
- **External editor catalogs are not updated.** If you also use a tool like BabelEdit, a move reads there as a delete plus an add and may drop per-key review state.
- **Key order is preserved.** Existing keys keep their position; new keys are appended at their natural nesting point.

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
