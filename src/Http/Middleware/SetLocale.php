<?php

namespace Webtack\Ecms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Webtack\Ecms\Content\LocalizedUrl;
use Webtack\Ecms\Content\PublicLanguages;
use Webtack\Ecms\Support\InterfaceTranslations;

/**
 * Sets the application locale for the current web request.
 *
 * Priority: authenticated user's saved locale, then the `locale` cookie,
 * then the best `Accept-Language` match, then `config('app.locale')`.
 * Values that are not in `config('ecms.locales')` are ignored.
 *
 * The public site (routes named `public.*`) is not affected by any of these, so the admin's saved language,
 * the `locale` cookie set by the admin switcher and the browser language never change the language of the site.
 * Without multilingual mode it uses `config('app.locale')`. With it, the interface strings follow the reading
 * language of the address ({@see LocalizedUrl}) when the host or the package has `{code}.json` for it ({@see InterfaceTranslations}), else the
 * base language's locale (the base language code when it has such a file, else `config('app.locale')`).
 *
 * Must run after session/cookie middleware and before `HandleInertiaRequests`,
 * which reads the resolved locale.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale(
            $request->routeIs('public.*') ? $this->publicLocale() : $this->resolve($request)
        );

        return $next($request);
    }

    /**
     * The interface locale of a public request.
     *
     * @example "en" on /en/... when the host or the package has en.json; the base language's locale otherwise
     */
    private function publicLocale(): string
    {
        $languages = app(PublicLanguages::class);

        if (! $languages->isEnabled()) {
            return config('app.locale');
        }

        $reading = app(LocalizedUrl::class)->lang();

        foreach ([$reading, $languages->base()] as $code) {
            if ($code !== config('content.default_lang') && $this->hasInterfaceFile($code)) {
                return $code;
            }
        }

        return config('app.locale');
    }

    /** Whether the host or the package has interface translations for a language code. */
    private function hasInterfaceFile(string $code): bool
    {
        return InterfaceTranslations::has($code);
    }

    /**
     * Resolve the locale code for the request.
     */
    private function resolve(Request $request): string
    {
        $available = array_keys((array) config('ecms.locales'));

        $candidates = [
            $request->user()?->locale,
            $request->cookie('locale'),
            $this->fromAcceptLanguage($request, $available),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && in_array($candidate, $available, true)) {
                return $candidate;
            }
        }

        return config('app.locale');
    }

    /**
     * Pick the first `Accept-Language` entry that matches an available locale,
     * comparing the base language when a region is given (`uk-UA` matches `uk`).
     *
     * `Request::getPreferredLanguage()` is not used because it returns the
     * first available locale when nothing matches, which would bypass
     * `APP_LOCALE`.
     *
     * @param  array<int, string>  $available
     */
    private function fromAcceptLanguage(Request $request, array $available): ?string
    {
        foreach ($request->getLanguages() as $language) {
            $base = strtolower(explode('_', $language)[0]);

            if (in_array($base, $available, true)) {
                return $base;
            }
        }

        return null;
    }
}
