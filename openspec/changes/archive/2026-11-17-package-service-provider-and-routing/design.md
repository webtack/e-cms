# Design

## Context

Today the application wires the CMS in four places (observed): `bootstrap/app.php` (three `web` middleware, `redirectGuestsTo('/login')`, `redirectUsersTo('/admin/dashboard')`, a `NotFoundHttpException` renderer), `routes/web.php` (284 lines, the public catch-all computed from `PublicPaths::reserved()` at load time and registered last), `AppServiceProvider` (singletons, `Gate::define` per `AccessResolver::SYSTEM` permission, `Gate::policy`, five Blade directives, `ThemeManager::registerViews()`), and `config/*.php`. See proposal.md for motivation.

Verified in `vendor/laravel/framework` (Laravel 13): `Authenticate::redirectUsing()`, `RedirectIfAuthenticated::redirectUsing()` and `AuthenticationException::redirectUsing()` are public static, so the redirects can be set from a provider (what `Middleware::redirectGuestsTo()` does internally); `Handler::renderable()` and `Router::pushMiddlewareToGroup()` exist. `Handler` has no public `shouldRenderJsonWhen()`; that part of `bootstrap/app.php` (JSON for `api/*`) is host policy and is not moved.

Verified in the frontend: `/admin/...`, `/login`, `/logout` are hardcoded in about fifty files under `resources/js` and in PHP (`LoginController`, `SidebarNavigation`, `ResolvesTypeSection`, `SiteSettingOwner`).

Theme facts: `ThemeManager` takes one root path (`config('theme.path')`, `base_path('themes')`) and builds `viewPaths()` as `<root>/<active>/views`, `<root>/default/views`. Translations: `HandleInertiaRequests::messages()` reads `lang_path("{$locale}.json")`.

## Goals / Non-Goals

**Goals:**
- `composer require` + discovery is enough to get the CMS routes, services, middleware and Gate in a Laravel 13 host.
- The public catch-all can never shadow a host route.
- Existing config keys and route names stay unchanged.

**Non-Goals:**
- A configurable admin prefix (see Decisions).
- Migrations, installer, built assets and the root view (steps 3–4).
- Changing authentication logic or UI.

## Decisions

**Auto-discovery.** `composer.json` → `extra.laravel.providers: ["Webtack\\Ecms\\EcmsServiceProvider"]`. Alternative: asking the host to add the provider — rejected, contradicts the "no manual edits" requirement.

**Admin prefix stays `admin`; switches instead.** A configurable prefix needs a base path shared via Inertia props and a rewrite of about fifty frontend files plus PHP strings, plus a rebuild of the dist. That is a feature on its own, not packaging. This change adds only `ecms.public_site` (default true) and `ecms.auth_routes` (default true). Alternative: configurable prefix now — rejected for scope/risk; recorded as future work in `docs/package-bootstrap.md`.

**`auth_routes` switch.** A host with Breeze/Fortify already has `/login`, `/logout` and the route name `login`; duplicates would clash. With `auth_routes=false` the package skips them, the admin still uses the `auth`/`verified` middleware, and the host's `login` route receives guests. The redirect callbacks are set only when `auth_routes` is true.

**Route order.** The provider loads service routes in `boot()` via `loadRoutesFrom()`. The public routes are registered in `$this->app->booted(...)`, which runs after every provider's `boot()`, so host routes defined in `routes/web.php` are already registered. Laravel resolves routes in registration order, so the catch-all is last. Note: when routes are cached (`route:cache`), the cache includes both; order is preserved. Alternative: `Route::fallback()` — rejected, it hides the 405 behaviour for `GET /logout`, which the current regex-based catch-all keeps.

**Language prefix.** `ResolvePublicLanguage` stays a *global* middleware, because it rewrites the request URI before routing (routes, route caching and controllers keep working on unprefixed paths). The provider registers it with `Kernel::prependMiddleware()` (the method exists in the framework's HTTP kernel), not through the host's `bootstrap/app.php`. Rules (owner decision, 2026-11-17):
- It is registered only when `ecms.public_site` is on.
- It must not break a host that has not migrated yet: when the package tables are missing it skips its work instead of querying `app_languages` (which a cache miss in `PublicLanguages` would do for any request whose first segment looks like a language code, e.g. `/api/...`).
- Language codes are reserved first segments for the host. `PublicPaths::languageCodeConflict()` also checks the first segments of the host's routes when a language is added, so a code already taken by a host route (`/en/contact`) cannot become an active or base language. This replaces any per-request check.
- Rejected: stripping the prefix for every path of the host (breaks the host's own `/en/...` routes), registering the public routes also under `/{lang}/…` (a refactor of the public controllers and the paginator, not verified), and matching the router on every request to find out who owns the path (duplicates routing).

**Reserved paths.** The catch-all pattern is built from `PublicPaths::reserved()` (config + the assets directory `vendor/ecms` added by the provider, because that directory exists only in the host's `public/`).

**Middleware.** `Router::pushMiddlewareToGroup('web', X)` for the three classes, guarded against duplicates (the router already ignores a class present in the group). `HandleInertiaRequests` stays last in the group, as before, because the provider boots after the framework's defaults.

**404 renderer.** Registered through `ExceptionHandler::renderable()` only when `public_site` is true, using the existing `NotFoundController`. The check "GET and no matching route" stays in the controller, unchanged.

**Services/Gate/policies/directives** move one-to-one from `AppServiceProvider` into `register()`/`boot()`. `Gate::define` closures take the framework `Authenticatable` (step 1 contract) instead of the concrete user class.

**Themes with two roots.** `ThemeManager` gets an ordered list of roots (`[application themes path, package themes path]`); `directory($theme)` returns the first root that contains `<theme>/theme.json`, so `viewPaths()` becomes `[active, default]` resolved per theme. The constructor keeps `(path, active, fallback)`-style semantics through a new `paths` argument; `config('theme.path')` stays the application path. Alternative: copy `default` into the host on install — rejected, the host would stop receiving fixes.

**Translations.** `HandleInertiaRequests::messages()` loads the package `lang/<locale>.json` and overlays the host's `lang_path(...)` file, host keys winning. The provider also calls `loadJsonTranslationsFrom()` for server-side `__()` use and `loadTranslationsFrom($path)` (no namespace) for the package `auth.php` and `validation.php`, in both `en` and `uk`, so `__('auth.failed')` and the validator keep reading the plain groups and no code changes. **Verified in `vendor/laravel/framework` (`ServiceProvider::loadTranslationsFrom`, `FileLoader`):** a namespace-less call goes to `addPath()`, which appends the package path after `[framework lang, host lang_path]`, and `loadPaths()` merges later paths over earlier ones. **Consequence (accepted, owner decision):** a key defined in the package `auth.php`/`validation.php` overrides the same key in the host's file; keys the package does not define are translated freely by the host, and the JSON dictionary stays host-wins. This limit is documented in the package docs. Rejected: a namespaced `ecms::auth.*` (does not work for `validation.*`, changes `LoginRequest`), a custom loader that puts the package path first (extra machinery), and shipping only `uk` (the owner wants both languages in the package).

**Interface locales.** The list of admin interface languages is the package's own config key `ecms.locales` (code => the language's own name, default `['en' => 'English', 'uk' => 'Українська']`), merged with the host's `ecms.*` so the host can extend or replace it. `SetLocale`, `LocaleController`, `HandleInertiaRequests` and `LanguageLinks` read `config('ecms.locales')` instead of `config('app.available_locales')`, so a stock host does not fail. `app.locale` and `app.fallback_locale` remain the framework's keys (they exist in every Laravel application). Whether a locale has translations is answered by one small helper (name to be chosen in the task, e.g. `Support/InterfaceTranslations` with `has(string $code): bool` and `messages(string $code): object`) that looks in the host's `lang_path()` and in the package `lang/`, the host's JSON keys winning; `SetLocale::hasInterfaceFile()` and `HandleInertiaRequests::messages()` both use it. Rejected: keeping `app.available_locales` with a default injected by the provider (the package would write into a section of the host's configuration it does not own).

**Config.** `mergeConfigFrom()` for every shipped file under its own key; `config/ecms.php` holds only the two switches for now. Because `config/files.php` and `config/content.php` contain class references, they resolve with the new namespace from step 1. Publish tags: `ecms-config`, `ecms-lang`, `ecms-views`.

Docs describing the components: `docs/admin-auth.md`, `docs/access-control.md`, `docs/site-themes.md`, `docs/public-site.md`, new `docs/package-bootstrap.md`.

## Risks / Trade-offs

- [Host already has `/admin`, `/login`] → `auth_routes=false` for login; `/admin` conflict is documented in `docs/package-bootstrap.md` as unsupported until the prefix becomes configurable.
- [`app->booted` hook runs after route caching on cached apps] → Verified in the design only; a test in step 5 runs `route:cache` in the test app and asserts the catch-all is last.
- [Redirect callbacks are process-global statics and override a host's own `redirectGuestsTo`] → Set only if the host has not set them (check via the default behaviour: the package sets them only when `auth_routes` is true), and documented.
- [Two theme roots complicate `theme:check`] → The command lists the root of each theme in its output; covered in tasks.
- [Gate abilities defined at boot rely on `AccessResolver::SYSTEM`] → unchanged from the app.

## Open Questions

- Whether host overrides of package views via `vendor/ecms` publishing are needed at all for admin pages (the admin is React). Does not affect specs or tasks.

### Audit 2026-11-17 (series deferred)

Findings from code added after this change was planned; each one changes the specs or tasks:

- **Global middleware `ResolvePublicLanguage` (blocking).** `bootstrap/app.php` prepends it to the global stack, before routing: it strips an active additional language prefix (`/en/...`) and 301-redirects the base code. The design moves only the three `web` group middleware. In a package it would see every request of the host: `/en/contact` becomes the host's `/contact`, and a first segment shaped like a language code (`/api/...` matches `[a-z]{2,3}`) triggers the multilingual check through `PublicLanguages`/`ContentLanguages` (database or cache), which fails before the package migrations ran. Owner decision needed: register it only when `public_site` is on and strip the prefix only for CMS routes (recommended), or keep stripping for all paths and document it. Add a `package-bootstrap` requirement either way. **Resolved (owner, 2026-11-17):** see "Language prefix" in Decisions; requirement "Language prefix of the public site", tasks 2.3 and 3.4.
- **`SetLocale` reads `lang_path()` (narrowed, resolved).** Since 2026-11-17 `SetLocale::hasInterfaceFile()` accepts a locale only when `lang_path("{code}.json")` exists. Checked in the code: it is used only by `publicLocale()` (the public site in multilingual mode); the admin language comes from `resolve()` and the configured locale list, not from files. Effect: without the package files in the check, `/en/...` on the public site falls back to the base language's locale. Resolved (owner, 2026-11-17): one helper that knows both the host's and the package's translation files serves `SetLocale` and `HandleInertiaRequests::messages()`; see "Interface locales" in Decisions and task 4.3.
- **`app.available_locales` does not exist in a stock host (new, resolved).** `config('app.available_locales')` is a key of the demo's own `config/app.php`. It is read by `SetLocale::resolve()` (`array_keys(null)` is a `TypeError` on every `web` request of the host, because the provider adds `SetLocale` to the `web` group), `LocaleController` (`Rule::in(array_keys(...))`), `HandleInertiaRequests` (empty language switcher) and `Theme\LanguageLinks` (language names fall back to the upper-cased code). Resolved (owner, 2026-11-17): the key moves into the package config as `ecms.locales`; see "Interface locales".
- **More public routes.** Besides `/` and `/{path}` the public part now has `/search`, `/sitemap`, `/sitemap.xml` (`PublicPaths::SITE_PATHS`). The `package-bootstrap` spec ("registers `/` and `/{path}`") and task 3.1 must list them.
- **Reserved paths.** `content.reserved_paths` now also holds `search`, `sitemap`, `sitemap.xml`, and content language codes form a separate reserved first-segment namespace (`PublicPaths::languageCodes()`). The "Reserved paths" requirement lists the old ten. Open: a host route whose first segment equals a language code is not detected by `PublicPaths::languageCodeConflict()`. **Resolved (owner, 2026-11-17):** the method also checks the first segments of the host's routes, task 3.4.
- **Provider contents.** `AppServiceProvider` gained `PublicLanguages`, scoped `LocalizedUrl` and the `ContentLanguages::USAGE_TAG` tag of six `LanguageUsages` checkers; task 1.1 must carry them.
- **Type cloning and two theme roots.** `ThemeTemplateCopier` copies type templates "in every theme under `config('theme.path')`". With the package `default` in its own root, the clone gets no copy of the package `default` templates for the type, and the copier must never write into the package. Owner decision needed: copy only within application themes, or copy the package `default` templates into an application `themes/default` (which then partially overrides the package theme). **Resolved (owner, 2026-11-17):** copy only within the application themes; the package is never written to. Verified in `app/Content/ThemeTemplateCopier.php`: it walks `config('theme.path')`, which stays the application path, so the package is untouched by construction, and the package `default` has no per-type templates (only generic `content/`, `blocks/block.blade.php`, …), so nothing is lost. A real defect found while checking: `File::directories()` on a missing `config('theme.path')` throws `DirectoryNotFoundException` (Symfony Finder), so a host that uses only the package `default` and has no `themes/` directory could not clone a type; task 4.5 makes the copier treat a missing directory as an empty plan.
- **Hardcoded paths.** The earlier counts (51, then 47) used different methods and go stale with every feature; the decision (the prefix stays `admin`) does not depend on the number. Not recorded here; measure with `grep -rlE "['\"`](/admin|/login|/logout)" resources/js` when needed. Not actionable.

#### Addendum (after security hardening, 2026-11-17)

- **Session invalidation.** `bootstrap/app.php` calls `authenticateSessions()` (other sessions end on a password change). A host's `bootstrap/app.php` does not, so the provider pushes `AuthenticateSession` to the `web` group (task 2.1; `Request limits`/`Middleware of the web group` in the spec).
- **Rate limiters.** The named limiters `search`, `upload`, `lookup`, `youtube` live in `AppServiceProvider::boot()` and read `config/limits.php`; routes use `throttle:<name>`. Without the provider registering them, the copied routes would reference undefined limiters (task 1.1). The behind-a-proxy note of `config/limits.php` (trusted proxies) belongs to the installer documentation.
- **User policy.** `Gate::policy(User::class, UserPolicy::class)` is bound to `UserModel::class()` (task 5.1).
- **Reserved paths.** `content.reserved_paths` now holds 14 entries, including `app` (since commit `33d9ccd`); the spec and task 3.2 list all of them.

#### Pre-start audit (2026-11-17, task 0.1)

Baseline commit: `45d2095`. `git diff --stat 124812e..45d2095` outside `openspec/` is empty: no code, config, route, middleware or docs changed since the security-hardening addendum, so the facts and counts of this series stay valid (532 files reference `App\` in `app/`, `config/`, `database/`, `routes/`, `themes/`, `resources/`, `tests/` and `bootstrap/` (564 with `docs/`); the next audit starts from this commit).
