<?php

namespace Webtack\Ecms\Content;

use Illuminate\Support\Facades\Cache;
use Webtack\Ecms\Http\Middleware\ResolvePublicLanguage;
use Webtack\Ecms\Models\AppLanguage;

/**
 * The languages of the public site as one cheap, cached snapshot: whether multilingual mode is on, the base
 * language code and the active additional languages (code and name, ordered by weight).
 *
 * Every public request asks for it before routing ({@see ResolvePublicLanguage}), so the
 * snapshot lives in the cache store and is dropped by {@see self::forget()} whenever a language, the switch or the
 * base code changes ({@see AppLanguage}, {@see ContentLanguages}). It is empty (`active` is `[]`) while the switch
 * is off, whatever languages are listed.
 *
 * @example
 *   $languages = app(PublicLanguages::class);
 *   $languages->isEnabled();       // true
 *   $languages->base();            // "uk"
 *   $languages->active();          // ['en', 'pl']
 *   $languages->find('EN');        // "en" (the stored code of an active language) | null
 */
class PublicLanguages
{
    private const CACHE_KEY = 'content.public_languages.active';

    /** @var list<array{code: string, name: string}>|null */
    private ?array $list = null;

    public function __construct(private readonly ContentLanguages $languages) {}

    /** Whether the site is multilingual (the switch is on); read from the settings, which a request loads once anyway. */
    public function isEnabled(): bool
    {
        return $this->languages->isMultilingual();
    }

    /** The code of the base language (`uk`); it is never a prefix. */
    public function base(): string
    {
        return $this->languages->baseLang();
    }

    /**
     * The codes of the active additional languages by weight; empty while multilingual mode is off.
     *
     * @return list<string>
     */
    public function active(): array
    {
        return array_column($this->activeWithNames(), 'code');
    }

    /**
     * The active additional languages with their names, by weight; empty while multilingual mode is off. Only the
     * list of languages is cached (it costs a query), and only when the switch is on.
     *
     * @return list<array{code: string, name: string}>
     */
    public function activeWithNames(): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        return $this->list ??= Cache::rememberForever(self::CACHE_KEY, fn () => AppLanguage::query()
            ->where('is_active', true)->orderBy('weight')->orderBy('id')->get(['code', 'name'])
            ->map(fn (AppLanguage $language) => ['code' => $language->code, 'name' => $language->name])->all());
    }

    /**
     * The stored code of an active additional language that a path segment names (case-insensitive), or null.
     */
    public function find(string $segment): ?string
    {
        $segment = strtolower($segment);

        foreach ($this->active() as $code) {
            if (strtolower($code) === $segment) {
                return $code;
            }
        }

        return null;
    }

    /** Whether the segment is the code of the base language (case-insensitive) while multilingual mode is on. */
    public function isBase(string $segment): bool
    {
        return $this->isEnabled() && strtolower($segment) === strtolower($this->base());
    }

    /** Drop the cached list of languages; the next read rebuilds it from the database. */
    public function forget(): void
    {
        $this->list = null;
        Cache::forget(self::CACHE_KEY);
    }
}
