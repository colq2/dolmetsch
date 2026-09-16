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
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get-translation')]
#[Description('Read a translation path. If the path is a leaf, returns its value for every locale that has one. If the path is a group, returns its immediate children only (one level deep, not the full subtree), each flagged is_group, paginated with limit/page.')]
#[IsReadOnly]
class GetTranslationTool extends Tool
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
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ], $this->domainValidationMessages());

        $result = $this->guardManagerErrors(fn () => $this->manager->get(
            $validated['domain'],
            $validated['path'],
            $validated['limit'] ?? 20,
            $validated['page'] ?? 1,
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
                ->description('The translation domain to read from.')
                ->required(),

            'path' => $schema->string()
                ->description('Dot-notation path to a leaf or a group, e.g. "app.messages" (backend) or "common.actions" (frontend).')
                ->required(),

            'limit' => $schema->integer()
                ->description('Max children to return when the path is a group. Default 20, max 100.')
                ->default(20),

            'page' => $schema->integer()
                ->description('Page number when the path is a group. Default 1.')
                ->default(1),
        ];
    }
}
