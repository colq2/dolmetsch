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
#[Version('0.1.0')]
#[Instructions('Deterministic CRUD + search over translation files (lang/{locale}/*.php and resources/js/Lang/{locale}.json). Always use these tools instead of hand-editing translation files directly, so JSON/PHP structure can never drift out of sync. Use search-translation or get-translation to locate a path before add/update/move/delete.')]
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
