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

#[Name('move-translation')]
#[Description('Move/rename a translation key. Copies its value to newPath (in newDomain if given, otherwise the same domain) for every locale that currently has it, then deletes it from the old path and prunes any now-empty parent groups. Locales missing the key are skipped, not errored. Does NOT update any external translation-editor catalog (e.g. a BabelEdit .babel project file) — such tools see a move as a delete plus an add and may drop per-key review state.')]
#[IsDestructive]
class MoveTranslationTool extends Tool
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
            'newPath' => ['required', 'string'],
            'newDomain' => ['sometimes', 'string', 'in:'.implode(',', $this->domainNames())],
        ], $this->domainValidationMessages());

        $result = $this->guardManagerErrors(fn () => $this->manager->move(
            $validated['domain'],
            $validated['path'],
            $validated['newPath'],
            $validated['newDomain'] ?? null,
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
                ->description('The translation domain the key currently lives in.')
                ->required(),

            'path' => $schema->string()
                ->description('Dot-notation path of the key to move.')
                ->required(),

            'newPath' => $schema->string()
                ->description('Dot-notation path to move the key to.')
                ->required(),

            'newDomain' => $schema->string()
                ->enum($this->domainNames())
                ->description('Move the key into a different domain. Omit to move within the same domain.'),
        ];
    }
}
