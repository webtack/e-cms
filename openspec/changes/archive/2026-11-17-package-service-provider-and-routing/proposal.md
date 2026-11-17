# Proposal

## Why

After step 1 the CMS code lives in the package, but nothing wires it into a Laravel application: the demo does that by hand in `bootstrap/app.php`, `routes/web.php`, `AppServiceProvider` and `config/auth.php`. A package must do this itself, so that `composer require webtack/e-cms` plus the installer is enough. This is step 2 of 7 (series in `package-scaffold-and-namespace`).

## What Changes

- Add `Webtack\Ecms\EcmsServiceProvider`, auto-discovered through `extra.laravel.providers` in `composer.json`. It takes over everything `AppServiceProvider` and `bootstrap/app.php` do for the CMS:
  - merges the package configuration (`content`, `files`, `theme`, `social_networks`) and adds `config/ecms.php` (feature switches);
  - registers the singleton/scoped services, the Gate abilities (one per system permission), model policies and the five Blade directives (`@field`, `@fields`, `@preview`, `@block`, `@menu`);
  - appends `EnsureUserIsActive`, `SetLocale`, `HandleInertiaRequests` to the `web` middleware group;
  - sets the guest and authenticated-user redirects (`/login`, `/admin/dashboard`) for the CMS routes and renders the public-site 404 for unmatched GET requests — both from the provider, not from `bootstrap/app.php`.
- Move the routes into `routes/` of the package, loaded by the provider: service routes (locale, login/logout, email verification, admin, image styles) always, the public-site routes (`/`, `/{path}`) only when `ecms.public_site` is on and always after every other route, including routes of the host.
- Make the theme lookup work with a theme directory in the package (`default`) and a theme directory in the application (`themes/`), the application one winning.
- Make `lang/*.json` and package views available from the package path (`HandleInertiaRequests` reads translations from the package, host overrides win).
- Wire the user contract and trait from step 1 into `Gate` and the policies.
- **Decision recorded:** the admin prefix stays `admin` in this series. The path `/admin/...`, `/login`, `/logout` is hardcoded in about fifty frontend files and in PHP (the count changes with every feature; measure it with `grep -rlE "['\"`](/admin|/login|/logout)" resources/js`); a configurable prefix needs a frontend refactor and is a separate future change. The planned "configurable prefix" is therefore replaced by switches that are possible without it: `public_site` and `auth_routes`.
- The reserved public paths (`content.reserved_paths`) keep protecting `admin`, `login`, … so the catch-all never shadows service routes.

## Capabilities

### New Capabilities

- `package-bootstrap`: how the installed package registers itself in a host application — services, routes and their order, middleware, redirects, the 404 of the public site, feature switches and theme/translation lookup.

### Modified Capabilities

- `site-themes`: the theme `default` is looked up in the package, and the application's theme directory takes priority; the "default is required and always last" rule stays.
- `public-site`: the public routes are optional and are registered after all other routes of the host.

## Impact

- New: `src/EcmsServiceProvider.php`, `config/ecms.php`, `routes/web.php` (split into service and public parts), `ThemeManager` changes (two theme roots).
- Changed: `HandleInertiaRequests` (translation path), `composer.json` (provider discovery).
- Docs (package): `docs/package-bootstrap.md` (new), updates to `docs/site-themes.md`, `docs/public-site.md`, `docs/admin-auth.md`, `docs/access-control.md`, `docs/README.md` index.
