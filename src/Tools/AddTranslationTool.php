<?php

namespace colq2\Dolmetsch\Tools;

use colq2\Dolmetsch\Tools\Concerns\InteractsWithTranslationDomains;
use colq2\Dolmetsch\TranslationManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('add-translation')]
#[Description('Add a brand new translation key. Errors if the path already exists (use update-translation instead). "translations" must include the main locale; other locales are optional. Path addressing: for the "backend" domain the first dot-segment is the group/filename (e.g. "app.messages.created" -> lang/{locale}/app.php); for the "frontend" domain the whole dot path indexes directly into the nested JSON file (e.g. "common.actions.add" -> resources/js/Lang/{locale}.json).')]
#[IsDestructive(false)]
#[IsIdempotent(false)]
class AddTranslationTool extends Tool
{
    use InteractsWithTranslationDomains;

    public function __construct(protected TranslationManager $manager)
    {
        //
    }

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'domain' => ['required', 'string', 'in:'.implode(',', $this->domainNames())],
            'path' => ['required', 'string'],
            'translations' => ['required', 'array', 'min:1'],
            'translations.*' => ['required', 'string'],
        ], $this->domainValidationMessages());

        $result = $this->guardManagerErrors(fn () => $this->manager->add(
            $validated['domain'],
            $validated['path'],
            $validated['translations'],
        ));

        return Response::structured($result);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()
                ->enum($this->domainNames())
                ->description('The translation domain to add the key to.')
                ->required(),

            'path' => $schema->string()
                ->description('Dot-notation path for the new key, e.g. "app.messages.created"')
                ->required(),

            'translations' => $schema->object(
                array_combine(
                    $this->localeCodes(),
                    array_map(
                        fn (string $locale): Type => $schema->string()->description("Translation text for locale \"{$locale}\"."),
                        $this->localeCodes(),
                    ),
                ),
            )
                ->description('Translation text keyed by locale code. Must include the configured main locale.')
                ->required(),
        ];
    }
}
