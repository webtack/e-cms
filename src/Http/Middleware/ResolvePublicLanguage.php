<?php

namespace Webtack\Ecms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Webtack\Ecms\Content\LocalizedUrl;
use Webtack\Ecms\Content\PresentContext;
use Webtack\Ecms\Content\PublicLanguages;
use Webtack\Ecms\Content\PublicPaths;
use Webtack\Ecms\Models\AppLanguage;

/**
 * Resolves the language prefix of a public address before routing.
 *
 * With multilingual mode on, a first path segment equal to the code of an active additional language is
 * removed from the request (so routes, route caching and controllers keep working on unprefixed paths) and
 * recorded as the reading language in {@see LocalizedUrl}. A first segment equal to the base language code
 * redirects permanently (301) to the same address without it, keeping the query string. The language is chosen
 * only by the address, never by a cookie or `Accept-Language`.
 *
 * It never strips the prefix before a service path (`/en/admin`, `/en/sitemap.xml`): the original path stays and ends
 * in the site 404. `/en/search` and `/en/sitemap` are the site pages that may carry a prefix. Everything else
 * (an unknown or inactive code, a prefix while the switch is off) is an ordinary address.
 *
 * The first check rejects every segment that cannot be a language code, so an ordinary request costs no query:
 * the languages come from a cached snapshot ({@see PublicLanguages}). The middleware is registered for every request of the
 * host (before routing), so it also does nothing while the package tables (`app_settings`, `app_languages`) do not
 * exist yet, e.g. before the migrations ran; the check runs only for a segment shaped like a language code.
 *
 * @example
 *   GET /en/about-us          → handled as GET /about-us in English
 *   GET /en                   → the home page in English
 *   GET /uk/about-us?x=1      → 301 /about-us?x=1 (base code `uk`)
 *   GET /en/admin/dashboard   → untouched, the site 404
 */
class ResolvePublicLanguage
{
    /** Whether the package tables exist; cached for the process once they are found (a negative answer is re-checked). */
    private static bool $tablesExist = false;

    public function __construct(
        private readonly PublicLanguages $languages,
        private readonly LocalizedUrl $url,
        private readonly PublicPaths $paths,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $path = trim($request->getPathInfo(), '/');
        $segments = explode('/', $path, 2);
        $first = $segments[0];
        $rest = $segments[1] ?? '';

        $lang = config('content.default_lang');

        if ($this->canBeCode($first) && $this->tablesExist() && $this->languages->isEnabled() && ! $this->isServicePath($rest)) {
            if ($this->languages->isBase($first)) {
                return redirect()->to($this->address($request, $rest), 301);
            }

            if (($code = $this->languages->find($first)) !== null) {
                $lang = $code;
                $this->strip($request, $rest);
            }
        }

        $this->record($lang);
        $this->prefixPaginator();

        return $next($request);
    }

    /** Whether the package tables the language lookup reads exist (a positive answer is kept for the process; a missing database counts as "no"). */
    private function tablesExist(): bool
    {
        if (self::$tablesExist) {
            return true;
        }

        try {
            return self::$tablesExist = Schema::hasTable('app_settings') && Schema::hasTable('app_languages');
        } catch (Throwable) {
            return false;
        }
    }

    /** A cheap shape check: length and character set of a language code. */
    private function canBeCode(string $segment): bool
    {
        return $segment !== ''
            && strlen($segment) <= AppLanguage::CODE_MAX_LENGTH
            && preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})*$/i', $segment) === 1;
    }

    /** Whether the path after the prefix starts with a service path (the site pages `search` and `sitemap` excepted). */
    private function isServicePath(string $rest): bool
    {
        $second = strtolower(explode('/', $rest, 2)[0]);

        return $second !== ''
            && in_array($second, $this->paths->reserved(), true)
            && ! in_array($second, PublicPaths::SITE_PATHS, true);
    }

    /** The address without the prefix, with the query string. */
    private function address(Request $request, string $rest): string
    {
        $query = $request->server->get('QUERY_STRING');

        return '/'.$rest.($query ? '?'.$query : '');
    }

    /** Re-initialise the request on the unprefixed path so path info and the matched route follow it. */
    private function strip(Request $request, string $rest): void
    {
        $server = $request->server->all();
        $server['REQUEST_URI'] = $this->address($request, $rest);

        $request->initialize(
            $request->query->all(),
            $request->request->all(),
            $request->attributes->all(),
            $request->cookies->all(),
            $request->files->all(),
            $server,
            $request->getContent(),
        );
    }

    /**
     * Make paginator links keep the prefix: the request now has the unprefixed path, so the base path of a page link
     * is the request URL with the language added. Set on every request so a long-lived process never keeps another
     * language's resolver.
     */
    private function prefixPaginator(): void
    {
        $url = $this->url;

        Paginator::currentPathResolver(function () use ($url) {
            $current = request()->url();

            if (! $url->isAdditional()) {
                return $current;
            }

            $root = rtrim(url('/'), '/');

            return $root.$url->path(substr($current, strlen($root)) ?: '/');
        });
    }

    /** Record the reading language; the per-language readers start fresh when it changes. */
    private function record(string $lang): void
    {
        $changed = $this->url->lang() !== $lang;
        $this->url->use($lang);

        if ($changed) {
            app(PresentContext::class)->readIn($lang, fallback: $this->url->isAdditional());
        }
    }
}
