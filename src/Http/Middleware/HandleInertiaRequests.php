<?php

namespace Webtack\Ecms\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Middleware;
use Webtack\Ecms\Access\AccessResolver;
use Webtack\Ecms\Navigation\SidebarNavigation;
use Webtack\Ecms\Settings\SiteSettings;
use Webtack\Ecms\Support\AdminAssets;
use Webtack\Ecms\Support\InterfaceTranslations;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit: the CMS's own view, so the host's
     * `resources/views/app.blade.php` is never used.
     *
     * @var string
     */
    protected $rootView = 'ecms::app';

    /**
     * Determines the current asset version: the hash of the published admin manifest (see {@see AdminAssets::version()}),
     * not the host's `public/build/manifest.json` that Inertia hashes by default.
     */
    public function version(Request $request): ?string
    {
        return app(AdminAssets::class)->version();
    }

    /**
     * Define the props that are shared by default.
     *
     * Only an explicit allow-list of user fields is exposed so hidden
     * attributes (password, remember_token) never reach the client. `auth.user.can` carries the user's
     * permissions (see docs/access-control.md).
     *
     * The `locale` prop carries the current interface language, the list of
     * available languages and the contents of `{current}.json` (the package's file with the host's `lang/{current}.json` keys on top, see
     * {@see InterfaceTranslations}), which the client-side `t()` helper reads (see docs/admin-i18n.md).
     *
     * The `site.name` prop is the site name from the site settings in the current language (fallback:
     * `config('app.name')`); it is shown in the sidebar header and on the login page.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatarUrl(),
                    // For showing and hiding elements only; the server decides every request again.
                    // Lazy: resolved only when a page is rendered.
                    'can' => fn () => app(AccessResolver::class)->forClient($user),
                ] : null,
            ],
            // The user's favorites and the admin menu, already filtered by access (see docs/admin-favorites.md).
            // Lazy: resolved only when a page is rendered.
            'navigation' => fn () => $user ? app(SidebarNavigation::class)->forUser($user) : null,
            'locale' => [
                'current' => app()->getLocale(),
                'available' => collect((array) config('ecms.locales'))
                    ->map(fn (string $name, string $code) => ['code' => $code, 'name' => $name])
                    ->values()
                    ->all(),
                'messages' => $this->messages(app()->getLocale()),
            ],
            // The site name in the current interface language, falling back to the application name.
            // Lazy and independent of the user, so the guest login page can show it too.
            'site' => [
                'name' => fn () => app(SiteSettings::class)->name() ?? config('app.name'),
            ],
            'richtext' => [
                'iframeHosts' => config('content.richtext.iframe_hosts', []),
            ],
            'flash' => [
                // A fresh id for every response that carries a message. The client keys its toast effect on it,
                // so two consecutive messages with equal text still produce two toasts (see docs/admin-shell.md).
                'id' => fn () => $request->session()->has('success') || $request->session()->has('error')
                    ? (string) Str::uuid()
                    : null,
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }

    /**
     * Load the JSON translations for a locale as an object, so an empty file
     * is serialized as `{}` rather than `[]`. The host's keys win over the package's.
     */
    private function messages(string $locale): object
    {
        return InterfaceTranslations::messages($locale);
    }
}
