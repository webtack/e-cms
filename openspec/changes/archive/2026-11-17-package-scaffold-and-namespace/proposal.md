# Proposal

## Why

The CMS is a complete Laravel application (`App\`, 400 files reference that namespace), so it cannot be installed into another project. To ship it as the Composer package `webtack/e-cms` the code first needs a package home, a package-safe namespace (`Webtack\Ecms\`, no clash with the host's `App\`) and no hard dependency on the host's `App\Models\User`. This is step 1 of 7 (see "Series" below); it only moves and renames code, wiring comes in later steps.

## What Changes

- Create the package repository skeleton in the sibling directory `/Users/ruslan/Documents/WebSites/e-cms/e-cms-package`: `composer.json` (`webtack/e-cms`, PSR-4 `Webtack\Ecms\` → `src/`), `src/`, `config/`, `database/{migrations,factories,seeders}`, `lang/`, `resources/`, `routes/`, `themes/default/`, `tests/`.
- **Copy** (not move) the reusable code from the demo app into the package and rename namespaces mechanically:
  - `App\…` → `Webtack\Ecms\…`, `Database\Factories\…` → `Webtack\Ecms\Database\Factories\…`, `Database\Seeders\…` → `Webtack\Ecms\Database\Seeders\…`.
  - Class names written as strings (`config/content.php`, `config/files.php`, theme `hooks.php`, docblocks in theme templates) are renamed too.
  - The documentation `docs/*.md` is copied in this step as well (all except the four demo-only files `e2e-testing.md`, `legacy-import.md`, `theme-dcculture.md` and `deployment.md`) and the same rename is applied, so that steps 2 to 5 edit the package's own copies in place; the demo-specific wording (`docker compose exec app`, `localhost:8080`) is cleaned in step 6.
  - The demo app keeps working untouched; its `app/` is deleted only in step 7.
- Excluded from the copy: `App\Models\User`, the `Legacy` import code (`app/Legacy`, `LegacyImportCommand`, `LegacyImportMap`, `config/legacy.php`), the Laravel-default migrations for `users`/`cache`/`jobs`, `themes/dcculture`, `e2e/`, and the translations of the demo theme (`lang/{en,uk}/dcculture.php`, `lang/{en,uk}/months.php`). The rule: the package copies what belongs to the admin panel and the theme `default`; everything else stays in the demo. From `lang/` the package takes `{en,uk}.json`, `{en,uk}/auth.php` and `{en,uk}/validation.php`.
- Models with `HasFactory` get an explicit factory binding (`#[UseFactory(...)]`), because Laravel's name-based factory discovery assumes `Database\Factories\`.
- Decouple from the concrete user class: every `App\Models\User` reference becomes the configured user model (`config('auth.providers.users.model')`, via a small resolver) and type hints become the `Webtack\Ecms\Contracts\EcmsUser` contract, which extends `Illuminate\Contracts\Auth\Authenticatable` (plain `Authenticatable` only where the user is arbitrary). The contract (`role()`, `favoriteTypes()`, `avatarUrl()`, `MEDIA_OWNER`) and the `Webtack\Ecms\Concerns\HasEcmsAccess` trait (`role()`, `favoriteTypes()`, `avatarUrl()`, `is_active` cast) are introduced here because the moved code needs them; the wiring is step 2.
- Make the Docker `app` container see the package: mount `../e-cms-package` so PHP tooling can run against it (and, later, so a Composer path repository symlink resolves inside the container).
- **BREAKING** (for themes only): theme `hooks.php` files must use `Webtack\Ecms\Theme\ThemeHooks` instead of `App\Theme\ThemeHooks`.

### Series

| # | Change | Result |
|---|--------|--------|
| 1 | `package-scaffold-and-namespace` (this) | Code lives in the package under `Webtack\Ecms\` |
| 2 | `package-service-provider-and-routing` | Provider, routes, middleware |
| 3 | `package-migrations-and-installer` | Migrations and `ecms:install` |
| 4 | `package-frontend-dist-and-root-view` | Built admin assets, own root view |
| 5 | `package-tests-on-testbench` | PHPUnit suite runs inside the package |
| 6 | `package-docs-and-history` | Documentation and OpenSpec history |
| 7 | `demo-app-consumes-package` | Demo requires the package; `app/` removed |

## Capabilities

### New Capabilities

- `package-structure`: the layout of the `webtack/e-cms` package, its Composer identity, the `Webtack\Ecms\` namespace rules, and what is (and is not) part of the package.

### Modified Capabilities

<!-- None: no existing runtime behaviour changes; the demo app is untouched in this step. -->

## Impact

- New repository directory `e-cms-package/` (outside this repository); files copied from `app/`, `config/`, `database/`, `docs/`, `lang/`, `resources/`, `routes/`, `themes/default/`.
- `docker-compose.yml` (volume for `../e-cms-package` in the `app` service).
- Docs: `docs/package-structure.md` (new, created in the package; the rest of `docs/` is copied from the demo in this step, see above) and a cross-link from `docs/site-themes.md` and `docs/theme-templates.md` about the renamed `ThemeHooks` namespace.
- Dependencies of the package: `php ^8.3`, `laravel/framework ^13.17`, `inertiajs/inertia-laravel ^3.5`, `intervention/image ^4.4`, `symfony/html-sanitizer ^8.1`.
