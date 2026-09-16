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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update-translation')]
#[Description('Update the text of an existing translation key for one or more locales. Errors if the path does not exist (use add-translation instead). At least one locale is required; locales not included are left untouched.')]
#[IsIdempotent(true)]
class UpdateTranslationTool extends Tool
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

        $result = $this->guardManagerErrors(fn () => $this->manager->update(
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
                ->description('The translation domain the key lives in.')
                ->required(),

            'path' => $schema->string()
                ->description('Dot-notation path of the existing key, e.g. "app.messages.created" (backend) or "common.actions.add" (frontend).')
                ->required(),

            'translations' => $schema->object(
                array_combine(
                    $this->localeCodes(),
                    array_map(
                        fn (string $locale): Type => $schema->string()->description("New translation text for locale \"{$locale}\"."),
                        $this->localeCodes(),
                    ),
                ),
            )
                ->description('New translation text keyed by locale code. At least one locale is required.')
                ->required(),
        ];
    }
}
