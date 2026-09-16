<?php

namespace colq2\Dolmetsch\Tools\Concerns;

use Closure;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

trait InteractsWithTranslationDomains
{
    /**
     * @return array<int, string>
     */
    protected function domainNames(): array
    {
        return array_keys(config('dolmetsch.domains', []));
    }

    /**
     * The union of every locale code configured across all domains, used to
     * build the `translations` input schema (e.g. `{locale: text}`).
     *
     * @return array<int, string>
     */
    protected function localeCodes(): array
    {
        return collect(config('dolmetsch.domains', []))
            ->flatMap(fn (array $domain): array => array_keys($domain['locales']))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Custom messages for the "domain"/"newDomain" `in:` validation rule, so
     * an unknown domain reads clearly instead of Laravel's generic "The
     * selected domain is invalid."
     *
     * @return array<string, string>
     */
    protected function domainValidationMessages(): array
    {
        $message = 'Unknown domain. Valid domains: '.implode(', ', $this->domainNames()).'.';

        return ['domain.in' => $message, 'newDomain.in' => $message];
    }

    /**
     * Runs a TranslationManager call, converting its InvalidArgumentException
     * (unknown domain/locale, path already/not existing, group delete without
     * recursive, ...) into a ValidationException so the real message reaches
     * the MCP client instead of a generic "internal server error".
     */
    protected function guardManagerErrors(Closure $callback): array
    {
        try {
            return $callback();
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw ValidationException::withMessages(['error' => $invalidArgumentException->getMessage()]);
        }
    }
}
