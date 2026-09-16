---
name: dolmetsch-translations
description: Add, rename, find, or delete translation keys in a Laravel app using Dolmetsch, keeping every locale in sync. Use whenever work touches lang/ files, JSON locale files, user-facing strings, or i18n keys.
---

# Dolmetsch Translations

## When to use this skill

Use it whenever a task involves translation keys: adding a user-facing string, renaming or
reorganising keys, finding what a key already says, or removing keys that a deleted feature
left behind. Also use it when you are about to write a literal user-facing string into a
Blade template, a controller, or a frontend component — that string almost certainly belongs
in a translation file instead.

Do **not** edit `lang/` files or JSON locale files directly. The whole point of these tools
is that they keep every locale structurally identical; a hand edit silently breaks that.

## Setup check

The MCP server registers under the handle in `config/dolmetsch.php` (`handle`, default
`translator`) and only in the environments listed in `register_local_server` (default
`['local']`). If the `translator` tools are not available, either the app is not running
locally or the handle differs — read the config rather than guessing.

Read `config/dolmetsch.php` first in any case. You need the domain names, the driver each
uses, and the locale codes, because every tool call takes a domain and writes are keyed by
locale code.

## Path addressing

Paths are dot notation, but where the file boundary sits depends on the driver:

| Driver | Files | Path meaning |
|---|---|---|
| `php` | `lang/{locale}/{group}.php` | first segment is the file — `app.messages.created` is `messages.created` inside `app.php` |
| `json` | `{path}/{locale}.json` | whole path indexes into the nested JSON — `common.actions.add` |

Getting this wrong is the most common mistake. On a `php` domain, a single-segment path like
`welcome` is a whole file, not a key.

## Workflows

### Adding a new string

1. **Search before you add.** `search-translation` with `by: "text"` for the English wording,
   then `by: "key"` for the path you had in mind. Near-duplicate keys are the main way
   translation files rot.
2. If nothing fits, choose a path consistent with its neighbours — run `get-translation` on
   the parent group to see how siblings are named before inventing a convention.
3. Call `add-translation` with the domain, the path, and a `translations` object keyed by
   locale code. **The main locale is required**; other locales are optional but include every
   one you can actually translate, since a missing locale is invisible until it renders.

`add-translation` errors if the path already exists. That error means your search missed
something — go read the existing key, do not switch to `update-translation` to force it
through.

### Changing existing text

`update-translation`, same shape as add. It errors if the path does not exist. Locales you
omit are left untouched, so updating one locale's wording will not blank the others.

### Renaming or relocating

`move-translation` takes `path` and `newPath`, plus `newDomain` to move across domains. It
copies the value for every locale that has it, deletes the old path, and prunes parents left
empty. Locales missing the key are skipped rather than erroring.

After a move, grep the codebase for the old path — `__('app.messages.created')`,
`@lang(...)`, `t('common.actions.add')`, and whatever your frontend helper is. The tools move
the data; they do not update call sites, and a stale call site renders the raw key to users.

**Caveat worth surfacing to the user:** if the project also uses an external translation
editor with its own catalog file (BabelEdit and similar), a move reads there as a delete plus
an add and may drop per-key review state.

### Deleting

`delete-translation` removes a leaf from every locale that has it. Deleting a *group* requires
`recursive: true`; without it the tool errors and reports how many keys would go, so you can
confirm the blast radius first. Treat that error as the confirmation step it is — report the
count to the user before retrying with `recursive: true`.

Check for call sites before deleting, same as with a move.

### Reading

`get-translation` on a leaf returns its value in every locale that has one — the fastest way
to spot a locale that is missing a key. On a group it returns immediate children only, one
level deep, paginated.

`search-translation` scans only the main locale, by design. To see what a key says in other
locales, search to find the path and then `get-translation` on it.

## From PHP

The same operations are available on the manager when you need them in application code,
a command, or a data migration:

```php
use colq2\Dolmetsch\TranslationManager;

$manager = app(TranslationManager::class);

$manager->add('backend', 'app.messages.saved', ['de_DE' => 'Gespeichert.', 'en_US' => 'Saved.']);
$manager->update('backend', 'app.messages.saved', ['en_US' => 'Saved!']);
$manager->move('backend', 'app.messages.saved', 'app.flash.saved');
$manager->delete('backend', 'app.flash.saved');
```

These write to files in the repository. Do not call them from request-handling code in
production.
