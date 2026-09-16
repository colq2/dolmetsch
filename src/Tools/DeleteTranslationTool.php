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

#[Name('delete-translation')]
#[Description('Delete a translation key from every locale that has it. Deleting a leaf works by default. Deleting a group (a path with children) requires recursive: true — without it, this errors and reports how many keys would be deleted, so you can confirm intent before retrying.')]
#[IsDestructive]
class DeleteTranslationTool extends Tool
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
            'recursive' => ['sometimes', 'boolean'],
        ], $this->domainValidationMessages());

        $result = $this->guardManagerErrors(fn () => $this->manager->delete(
            $validated['domain'],
            $validated['path'],
            $validated['recursive'] ?? false,
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
                ->description('The translation domain the key lives in.')
                ->required(),

            'path' => $schema->string()
                ->description('Dot-notation path of the key (or group) to delete.')
                ->required(),

            'recursive' => $schema->boolean()
                ->description('Required to be true when deleting a group (a path with children). Default false.')
                ->default(false),
        ];
    }
}
