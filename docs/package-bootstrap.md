# Package bootstrap

How the installed package `webtack/e-cms` registers itself in a Laravel host application. The layout of the package: [package-structure.md](package-structure.md).

## Automatic registration

`composer.json` lists `Webtack\Ecms\EcmsServiceProvider` in `extra.laravel.providers`, so Laravel package discovery registers it. The host edits none of its `bootstrap/app.php`, `routes/web.php`, service providers or middleware lists. The provider (`src/EcmsServiceProvider.php`) does the following.

`register()`:

- merges the configuration files `content`, `files`, `theme`, `social_networks`, `limits` and `ecms` under their own keys (the host overrides a top-level key by publishing or defining it; other keys keep the package defaults);
- binds the services (`AccessResolver`, `AppSettings`, `SiteSettings`, `FileProfiles`, `FileStorage`, `ContentLanguages`, `PublicLanguages`, the scoped `LocalizedUrl`, the scoped theme renderers, `FieldTypeRegistry`, `ThemeManager`) and tags the six language-usage checkers with `ContentLanguages::USAGE_TAG`.

`boot()`:

- Gate: one ability per system permission (`AccessResolver::SYSTEM`) and the policies for `ContentNode`, `File` and the host user model; `Route::model('user', …)` binds `{user}` to the host user model, because the controllers type the parameter as the contract `EcmsUser` ([access-control.md](access-control.md#host-user-model));
- the named rate limiters `search`, `upload`, `lookup`, `youtube` (numbers from `limits.*_per_minute`; the key is the user id when signed in, the IP address otherwise);
- the Blade namespace `theme` and the directives `@field`, `@fields`, `@preview`, `@block`, `@menu`;
- the translations ([admin-i18n.md](admin-i18n.md#override-rule));
- the `web` middleware, the redirects, the 404 page and the language prefix (below);
- the routes (below), the console commands `theme:check` and `image-styles:flush`, and the publish tags.

Publish tags: `ecms-config` (the six config files), `ecms-lang` (`lang/`), `ecms-views` (`resources/views`, to `resources/views/vendor/ecms`).

## Feature switches (`config/ecms.php`)

| Key | Environment | Default | Effect |
|-----|-------------|---------|--------|
| `public_site` | `ECMS_PUBLIC_SITE` | `true` | The public routes, the language prefix of public addresses and the theme 404 page are registered. Off: none of them, and the standard Laravel 404 is used. |
| `auth_routes` | `ECMS_AUTH_ROUTES` | `true` | `/login`, `/logout`, the email verification notice and the redirects to `/login` and `/admin/dashboard` are registered. Off: a host with its own authentication (Breeze, Fortify) keeps its own `login` route and redirects. |
| `locales` | — | `en`, `uk` | The admin interface languages, code => the language's own name ([admin-i18n.md](admin-i18n.md)). |

The admin prefix is fixed: `admin`. The paths `/admin/...`, `/login` and `/logout` are written in about fifty frontend files and in PHP, so a configurable prefix needs a frontend refactor and a rebuild of the compiled assets; it is a separate future change. A host that already has a `/admin` route is not supported until then.

## Middleware of the `web` group

The provider pushes these classes to the `web` group, once each (the router ignores a class that is already in the group):

1. `Illuminate\Session\Middleware\AuthenticateSession` — ends the other sessions of a user whose password changed. It is skipped when the host already has the alias `auth.session` in the group (`authenticateSessions()`). See [admin-auth.md](admin-auth.md#sessions-after-a-password-change).
2. `EnsureUserIsActive` — a deactivated user is logged out and sent to `/login`.
3. `SetLocale` — the request locale.
4. `HandleInertiaRequests` — the shared Inertia data (last in the group).

## Redirects and the 404 page

With `auth_routes` on, the provider sets the static callbacks `Authenticate::redirectUsing`, `AuthenticateSession::redirectUsing` and `AuthenticationException::redirectUsing` (guests go to `/login`) and `RedirectIfAuthenticated::redirectUsing` (signed-in users go to `/admin/dashboard`). They are process-wide, so they replace a `redirectGuestsTo()`/`redirectUsersTo()` of the host: a host that wants its own redirects sets `ECMS_AUTH_ROUTES=false`.

With `public_site` on, the provider adds a renderer for `NotFoundHttpException` that serves the theme's `errors/404` for a browser GET/HEAD outside the service prefixes (`NotFoundController::render()`, [public-site.md](public-site.md#routes)). JSON for `api/*` (`shouldRenderJsonWhen`) is host policy and is not touched.

## Routes and their order

The routes are split into two files, both loaded inside the `web` middleware group:

- `routes/service.php`, loaded in `boot()`, always: the interface language (`POST /locale`), the authentication routes (only with `auth_routes`), the admin panel under `admin` with its `throttle:upload`, `throttle:lookup` and `throttle:youtube` routes, and the image style route (`image-styles.show`, without the session and site middleware).
- `routes/public.php`, loaded in `$app->booted()` and only with `public_site`: `/`, `/search` (`throttle:search`), `/sitemap`, `/sitemap.xml` and the catch-all `/{path}`.

`booted()` callbacks run after every provider has booted and after the host's `routes/web.php` has been loaded, so the public routes are registered after every other route. Laravel matches routes in registration order, so a host route such as `GET /contact` wins over the catch-all `/{path}`. When routes are cached (`route:cache`) the cache contains both files in the same order.

Limit: Laravel keeps one route per method and URI, and the later registration replaces the earlier. A host route with exactly the same method and URI as a public route (`GET /`, `GET /search`, `GET /sitemap`, `GET /sitemap.xml`) is therefore replaced by the CMS one. The stock `Route::get('/', …)` of a new Laravel application is such a route: remove it, or set `ECMS_PUBLIC_SITE=false`.

`GET /logout` answers 405 (the route accepts only POST), not the theme's 404: that is why the public part is a regex route and not `Route::fallback()`.

The first segment of the public catch-all must not be a reserved path (`content.reserved_paths`, [content-model.md](content-model.md#reserved-paths)). `PublicPaths::reserved()` adds the first segment of the directory the package assets are published to (`vendor/ecms`, so `vendor`).

## Language prefix of public addresses

`ResolvePublicLanguage` strips an active additional language prefix (`/en/about-us` → `/about-us`) before routing, so it must be global middleware. With `public_site` on the provider prepends it with `Kernel::prependMiddleware()`; with it off, no request is rewritten. The middleware does nothing while the tables `app_settings` or `app_languages` do not exist (before the migrations ran), so a request such as `GET /api/x` of a host that has not migrated yet is answered by the host. Details: [public-site.md](public-site.md#language-prefix).

Language codes are reserved first segments of the application. When a language is added, `PublicPaths::languageCodeConflict()` refuses a code that equals the first segment of a route of the host (`/en/contact` rules out `en`), in addition to the existing checks (reserved path, page slug, type slug). The routes of the package itself are not counted.

## Themes and translations

Themes are looked up in two roots, the application's `themes/` first and the package's `themes/` second ([site-themes.md](site-themes.md#theme-roots)). Interface translations come from the package, and the host's `lang/` overrides ([admin-i18n.md](admin-i18n.md#override-rule)).

## Conflicts with the host

| Host has | Result |
|----------|--------|
| its own login (`/login`, route `login`) | set `ECMS_AUTH_ROUTES=false` |
| `GET /` (the stock welcome route), `/search`, `/sitemap`, `/sitemap.xml` | replaced by the public routes; remove it or turn the public site off |
| any other route, such as `/contact` | wins over `/{path}` |
| a route `/en/...` | the language `en` cannot be added |
| a `/admin` prefix | not supported (fixed prefix) |
| its own `redirectGuestsTo()` / `redirectUsersTo()` | replaced while `auth_routes` is on |
| no `app.available_locales` in `config/app.php` | nothing to do: the package reads `ecms.locales` |

## Tests

The behaviour on this page is locked in by `tests/Feature/Package/*` (registration, switches, route order with cached routes, theme roots, translations, locales, language prefix); see [package-testing.md](package-testing.md).
