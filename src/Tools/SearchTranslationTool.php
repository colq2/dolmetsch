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

#[Name('search-translation')]
#[Description('Search translations by value text or by key path. Only scans the main/source locale of each domain (structure is assumed to mirror across locales) — use get-translation to see a specific path in every locale. Paginated with limit/page.')]
#[IsReadOnly]
class SearchTranslationTool extends Tool
{
    use InteractsWithTranslationDomains;

    public function __construct(protected TranslationManager $manager)
    {
        //
    }

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'query' => ['required', 'string'],
            'by' => ['sometimes', 'string', 'in:text,key'],
            'domain' => ['sometimes', 'string', 'in:'.implode(',', $this->domainNames())],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ], $this->domainValidationMessages());

        $result = $this->guardManagerErrors(fn () => $this->manager->search(
            $validated['query'],
            $validated['by'] ?? 'text',
            $validated['domain'] ?? null,
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
            'query' => $schema->string()
                ->description('The text or key fragment to search for (case-insensitive substring match).')
                ->required(),

            'by' => $schema->string()
                ->enum(['text', 'key'])
                ->description('Search translation "text" (default) or the dot-notation "key" path.')
                ->default('text'),

            'domain' => $schema->string()
                ->enum($this->domainNames())
                ->description('Restrict the search to one domain. Omit to search every configured domain.'),

            'limit' => $schema->integer()
                ->description('Max results to return. Default 20, max 100.')
                ->default(20),

            'page' => $schema->integer()
                ->description('Page number. Default 1.')
                ->default(1),
        ];
    }
}
