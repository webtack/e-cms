<?php

namespace Webtack\Ecms\Content;

use Webtack\Ecms\Http\Middleware\ResolvePublicLanguage;

/**
 * The reading language of the current public request and the prefixing of internal addresses with it.
 *
 * The language comes only from the address ({@see ResolvePublicLanguage} records it): `default`
 * (the base language, no prefix) or the code of an active additional language (`/en/...`). The same object is the
 * single place that turns an internal address into the address of the page's language, so menus, breadcrumbs,
 * cards, pagination, search, the sitemap page, references and rich text all agree.
 *
 * {@see self::path()} prefixes only an internal address that starts with `/`; absolute URLs, anchors, `mailto:`,
 * relative paths, service paths (`/admin`, `/storage/...`, `/sitemap.xml`) and addresses that already carry a language
 * are returned unchanged. The base language never gets a prefix.
 *
 * @example
 *   $url = app(LocalizedUrl::class);
 *   $url->path('/about-us');                 // "/en/about-us" on an English page, "/about-us" otherwise
 *   $url->path('/about-us', 'pl');           // "/pl/about-us"
 *   $url->path('https://example.org');       // unchanged
 *   $url->lang();                            // "en" ("default" for the base language)
 *   $url->code();                            // the value of <html lang>
 */
class LocalizedUrl
{
    private string $lang = 'default';

    public function __construct(
        private readonly PublicLanguages $languages,
        private readonly PublicPaths $paths,
    ) {}

    /** Set the reading language (`default` or the code of an active additional language). */
    public function use(string $lang): void
    {
        $this->lang = $lang;
    }

    /** The reading language as values are stored: `default` for the base language, else the language code. */
    public function lang(): string
    {
        return $this->lang;
    }

    /** Whether the page is read in an additional language (it carries a prefix and reads with the fallback). */
    public function isAdditional(): bool
    {
        return $this->lang !== config('content.default_lang');
    }

    /**
     * The code of the document language (`<html lang>`): the reading language code; for the base language the
     * saved base code, and on a single-language site the application locale as before.
     *
     * @example $url->code(); // "uk" | "en" | "en"
     */
    public function code(): string
    {
        if ($this->isAdditional()) {
            return $this->lang;
        }

        return $this->languages->isEnabled() ? $this->languages->base() : str_replace('_', '-', app()->getLocale());
    }

    /** The address prefix of the reading language: `''` for the base language, else `/en`. */
    public function prefix(): string
    {
        return $this->prefixOf($this->lang);
    }

    /**
     * An internal address in a language (the reading language by default).
     *
     * @param  string  $url  The stored address, usually `/about-us`; anything else is returned unchanged.
     * @param  string|null  $lang  `default` or a language code; null = the reading language.
     */
    public function path(string $url, ?string $lang = null): string
    {
        $prefix = $this->prefixOf($lang ?? $this->lang);

        if ($prefix === '' || ! $this->isInternal($url)) {
            return $url;
        }

        $tail = (string) preg_replace('/^[^?#]*/', '', $url, 1);
        $path = substr($url, 0, strlen($url) - strlen($tail));

        return $prefix.($path === '/' ? '' : $path).$tail;
    }

    /**
     * Prefix the internal links of sanitized rich text: every `<a href="/...">` gets the prefix of the reading
     * language by the rules of {@see self::path()}. Absolute links, anchors, `mailto:`, links to files and service
     * paths stay as they are. The HTML is the output of {@see HtmlSanitizer} (double-quoted attributes), and only
     * the start of an `href` value is rewritten, so the rest of the markup is never re-serialised.
     *
     * @example $url->html('<p><a href="/about-us">About</a></p>'); // '<p><a href="/en/about-us">About</a></p>' on an English page
     */
    public function html(string $html): string
    {
        if ($this->prefix() === '' || ! str_contains($html, 'href="/')) {
            return $html;
        }

        return (string) preg_replace_callback(
            '/(<a\b[^>]*?\shref=")(\/[^"]*)(")/i',
            function (array $match) {
                $raw = $match[2];

                if ($this->path(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5)) === html_entity_decode($raw, ENT_QUOTES | ENT_HTML5)) {
                    return $match[0];
                }

                // Only the prefix is added to the raw (still escaped) value; `/` and `/?x` lose their lone slash.
                return $match[1].$this->prefix().(preg_match('/^\/(?:[?#]|$)/', $raw) === 1 ? substr($raw, 1) : $raw).$match[3];
            },
            $html,
        );
    }

    private function prefixOf(string $lang): string
    {
        return $lang === config('content.default_lang') || ! $this->languages->isEnabled() ? '' : '/'.$lang;
    }

    /** Whether the address is a site path that belongs in a language: `/...`, not a service path or a language address. */
    private function isInternal(string $url): bool
    {
        if ($url === '' || $url[0] !== '/' || str_starts_with($url, '//')) {
            return false;
        }

        $first = strtolower(explode('/', ltrim((string) preg_replace('/[?#].*$/s', '', $url), '/'))[0]);

        if ($first === '') {
            return true;
        }

        if (in_array($first, $this->paths->reserved(), true) && ! in_array($first, PublicPaths::SITE_PATHS, true)) {
            return false;
        }

        // An address that already names a language (`/en/about`) is left alone, never prefixed twice.
        return $this->languages->find($first) === null && ! $this->languages->isBase($first);
    }
}
