<?php

namespace Webtack\Ecms\Theme;

use Webtack\Ecms\Content\LocalizedUrl;
use Webtack\Ecms\Content\PublicLanguages;
use Webtack\Ecms\Content\TranslatedLanguages;
use Webtack\Ecms\Models\ContentNode;

/**
 * The language data of a public page: the list for a switcher, the `hreflang` alternates and the canonical address.
 *
 * Everything is empty while multilingual mode is off (or no additional language is active). The languages are the base
 * language first, then the active ones by weight. A page has a translation in a language when its node has at least
 * one stored value in it ({@see TranslatedLanguages}); a page without a node (home, a list, search, the sitemap) counts as
 * translated everywhere. The address of the same page in each language is the unprefixed request path in that language
 * ({@see LocalizedUrl::path()}).
 *
 * - `languages`: `code`, `lang` (the storage key: `default` or the code), `name`, `current`, `translated`, `url` (relative,
 *   with the query string, for a switcher link; an untranslated language still leads to the fallback page).
 * - `alternates`: `hreflang` and absolute `url` for the base language and every language the page is translated into,
 *   plus `x-default` pointing at the base-language address; a page served in a language without a translation is not listed.
 * - `canonical`: the absolute address of the page itself when it has a translation in the current language, otherwise the
 *   base-language address (so the fallback page is not duplicate content).
 *
 * @example
 *   app(LanguageLinks::class)->make($node);
 *   // ['languages' => [['code' => 'uk', 'lang' => 'default', 'name' => 'Українська', 'current' => false, 'translated' => true, 'url' => '/about-us'], …],
 *   //  'alternates' => [['hreflang' => 'uk', 'url' => 'https://example.org/about-us'], ['hreflang' => 'x-default', …]],
 *   //  'canonical' => 'https://example.org/en/about-us']
 */
class LanguageLinks
{
    public function __construct(
        private readonly PublicLanguages $languages,
        private readonly LocalizedUrl $url,
        private readonly TranslatedLanguages $translated,
    ) {}

    /**
     * @param  ContentNode|null  $node  The node the page shows, if any.
     * @return array{languages: list<array<string, mixed>>, alternates: list<array{hreflang: string, url: string}>, canonical: string|null}
     */
    public function make(?ContentNode $node = null): array
    {
        $active = $this->languages->activeWithNames();

        if ($active === []) {
            return ['languages' => [], 'alternates' => [], 'canonical' => null];
        }

        $path = '/'.ltrim(request()->path(), '/');
        $query = request()->getQueryString();
        $translatedInto = $node === null ? null : ($this->translated->forNodes([$node->id])[$node->id] ?? []);
        $base = config('content.default_lang');

        $languages = [[
            'code' => $this->languages->base(),
            'lang' => $base,
            'name' => $this->baseName(),
            'current' => ! $this->url->isAdditional(),
            'translated' => true,
            'url' => $this->url->path($path, $base).($query ? '?'.$query : ''),
        ]];

        foreach ($active as $language) {
            $languages[] = [
                'code' => $language['code'],
                'lang' => $language['code'],
                'name' => $language['name'],
                'current' => $this->url->lang() === $language['code'],
                'translated' => $translatedInto === null || in_array($language['code'], $translatedInto, true),
                'url' => $this->url->path($path, $language['code']).($query ? '?'.$query : ''),
            ];
        }

        $alternates = [];

        foreach ($languages as $language) {
            if ($language['translated']) {
                $alternates[] = ['hreflang' => $language['code'], 'url' => url($this->url->path($path, $language['lang']))];
            }
        }

        $alternates[] = ['hreflang' => 'x-default', 'url' => url($this->url->path($path, $base))];

        $current = collect($languages)->firstWhere('current', true);

        return [
            'languages' => $languages,
            'alternates' => $alternates,
            'canonical' => url($this->url->path($path, $current['translated'] ? $current['lang'] : $base)),
        ];
    }

    /** The display name of the base language: the interface name when the application knows the code, else the upper-case code. */
    private function baseName(): string
    {
        $code = $this->languages->base();

        return (string) (config('ecms.locales')[$code] ?? strtoupper($code));
    }
}
