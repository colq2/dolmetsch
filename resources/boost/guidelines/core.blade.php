## Dolmetsch

Dolmetsch manages this application's translation files structurally. It exposes an MCP
server (default handle `translator`) with six tools, plus a `TranslationManager` you can
call from PHP.

### Do not hand-edit translation files

Never write to `lang/` or a JSON locale file directly — not with a text edit, not with a
script. Every key must exist in the same shape across every locale, and hand edits are how
that invariant breaks: a key lands in one locale but not another, a rename orphans its old
parent group, a PHP array drifts from its JSON counterpart.

Use the `translator` MCP tools, or `TranslationManager` if you are writing PHP. Both keep
all locales in step and prune parent groups that a delete or move leaves empty.

### Domains and path addressing

A **domain** is one area of translation storage, configured in `config/dolmetsch.php`. Each
names a driver, a base path and a locale map. Every tool takes the domain as an argument.

- `php` driver — files live at `lang/{locale}/{group}.php`. The **first** dot-segment of a
  path is the file: `app.messages.created` means `messages.created` inside `lang/{locale}/app.php`.
- `json` driver — one file per locale at `{path}/{locale}.json`. The **whole** dot path
  indexes into the nested JSON: `common.actions.add`.

`main_locale` is the source of truth. Search and group listings scan only that locale.

### Adding vs. updating

`add-translation` fails if the path already exists; `update-translation` fails if it does
not. This is deliberate — pick the right one rather than treating either as an upsert. If
you are unsure whether a key exists, call `search-translation` or `get-translation` first.

@verbatim
<code-snippet name="Adding a key from PHP" lang="php">
app(colq2\Dolmetsch\TranslationManager::class)->add('backend', 'app.messages.saved', [
    'de_DE' => 'Gespeichert.',
    'en_US' => 'Saved.',
]);
</code-snippet>
@endverbatim

### Before inventing a new key

Search first. Translation files accumulate near-duplicates fast, and an agent adding
`app.messages.saved` next to an existing `app.flash.saved` is the usual cause. Reuse the
existing key when one fits.
