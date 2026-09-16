<?php

namespace colq2\Dolmetsch\Servers;

use colq2\Dolmetsch\Tools\AddTranslationTool;
use colq2\Dolmetsch\Tools\DeleteTranslationTool;
use colq2\Dolmetsch\Tools\GetTranslationTool;
use colq2\Dolmetsch\Tools\MoveTranslationTool;
use colq2\Dolmetsch\Tools\SearchTranslationTool;
use colq2\Dolmetsch\Tools\UpdateTranslationTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Translator')]
#[Version('1.0.0')]
#[Instructions('Deterministic CRUD + search over the translation files of this application, across every domain configured in config/dolmetsch.php (php domains at lang/{locale}/{group}.php, json domains at {path}/{locale}.json). Always use these tools instead of hand-editing translation files directly, so the key structure can never drift between locales. Use search-translation or get-translation to locate a path before add/update/move/delete.')]
class TranslationsServer extends Server
{
    protected array $tools = [
        AddTranslationTool::class,
        UpdateTranslationTool::class,
        MoveTranslationTool::class,
        DeleteTranslationTool::class,
        SearchTranslationTool::class,
        GetTranslationTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
