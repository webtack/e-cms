# Design

## Context

Current state (verified in code): `config/app.php` has `locale = env('APP_LOCALE','en')` and `fallback_locale = en`; there is no `lang/` directory, but `LoginRequest` already uses `__('auth.failed')` and `__('auth.throttle')`. `resources/views/app.blade.php` sets `<html lang>` from `app()->getLocale()`. All React strings (`Login`, `VerifyEmail`, `Dashboard`, `Profile`, `UserMenu`, `ThemeToggle`, `AdminHeader`, `AppSidebar`) and the titles in `navigation.ts` are English literals. `HandleInertiaRequests` shares `auth.user` (an explicit allow-list of fields) and `flash`. `User` has no locale field. The motivation is in `proposal.md`, the behaviour is in `specs/admin-i18n` and `specs/admin-shell`.

The components are described in the new/updated `docs/admin-i18n.md` and `docs/admin-shell.md`; the login is in `docs/admin-auth.md`.

## Goals / Non-Goals

**Goals:**
- One set of translations (`lang/*.json`) for React; server messages are the standard `lang/{locale}/*.php`.
- Adding a third language = adding an entry to the config, `lang/xx.json` (a copy of `en.json`) and, if needed, `lang/xx/*.php`, without code changes.
- New screens are written through `t()` from the start; a missing translation never breaks a page.

**Non-Goals:**
- Translating CMS content, localized URLs, dates/numbers (`Intl`), plurals, RTL, SSR.
- Choosing the language in the profile as a separate form (the profile is a stub; the switcher is in the header and on the login page).
- Automatic generation/extraction of keys from pages (a tool like i18next-parser).

## Decisions

- **Language config.** `config/app.php`: `available_locales` = `['en' => 'English', 'uk' => 'Українська']` (native names for the switcher). `locale` stays `env('APP_LOCALE','en')`; `fallback_locale = en`. Alternative: a separate `config/locales.php`: an extra file for what is an extension of the app config.
- **Keys are English phrases** (`"Log in": "Увійти"`), as in Laravel JSON translations; `en.json` contains the same keys with the same values. Alternative: dotted keys (`auth.login.title`): more stable against text edits, but harder to read in code and a wider gap between the key and the visible text. `en.json` is needed explicitly so that the key-parity test has a reference and so that copying `en.json` works as a template for a new language.
- **Locale detection — the `SetLocale` middleware** in the `web` group, **before** `HandleInertiaRequests` (because that one reads `app()->getLocale()`). Order: `Auth::user()?->locale` → cookie `locale` → `$request->getPreferredLanguage(array_keys(available_locales))` → `config('app.locale')`; each value is checked for membership in `available_locales`. `app()->setLocale()` is applied per request. `getPreferredLanguage` must map `uk-UA` to `uk`; this is verified by a test during implementation.
- **Storage.** Column `users.locale` — `string(5)`, nullable (null = "not chosen", then detection by cookie/Accept-Language applies). A new migration; existing migrations are not touched. For a guest — the cookie `locale` (`Cookie::forever`, through the standard cookie encryption). For an authenticated user the cookie is set at the same time, so after logout the login page stays in the same language. The "session only" alternative was rejected: the session is destroyed on logout and the language would be lost precisely on the login page.
- **`POST /locale` (`LocaleController`, name `locale.update`)** outside the `auth` group (guests need it). Validation `Rule::in(array_keys(available_locales))`; response `back()` (302 is safe for an Inertia POST). `throttle:30,1` against abuse. This changes state → POST, not a GET link.
- **Delivery to React — a shared `locale` prop** in `HandleInertiaRequests::share`: `{ current, available: [{code, name}], messages }`, where `messages` = the contents of `lang/{current}.json` (the file is returned as is, including for `en`, so that `t()` has no special branches). The JSON size is small; if it grows (hundreds of keys), switch to splitting by page — a separate change.
- **Client.** `lib/i18n.ts` — a pure function `translate(messages, key, replace?)`: `messages[key] ?? key`, then replacement of `:name` (longer names first, as in Laravel, so that `:minutes` does not match `:min`). `hooks/useTranslation.ts` reads `usePage().props.locale` and returns `{ t, locale, locales }`. The alternative `react-i18next` was rejected by the owner: a second source of translations next to `lang/`.
- **Navigation.** `navigation.ts` keeps **keys** (English phrases) in `title`; the components (`AppSidebar`, `Breadcrumbs`) do the translation via `t(item.title)`. `getBreadcrumbs` still returns keys — the function stays pure and does not depend on React. The "Admin" title is also a key.
- **Switcher.** `Components/Admin/LocaleSwitcher.tsx` — a DropdownMenu (`ui/dropdown-menu` exists) with a `Languages` icon; the selected language is marked with a check; the action is `router.post('/locale', { locale }, { preserveScroll: true, preserveState: true })`. The persistent layout is not remounted, so the sidebar state is kept. On the login page the same component sits in the corner of the page (outside `AdminLayout`).
- **`<Head title>`** and `aria-label` also go through `t()`. Server messages: `lang/uk/auth.php` (full translation), `lang/uk/validation.php` (the rules that are actually used + `attributes`); missing keys fall back to `en`. The English `lang/en/auth.php`/`validation.php` are published with `php artisan lang:publish` (Laravel 13 does not include them in the skeleton).
- **Default value.** `APP_LOCALE` stays `en`: the e2e and current tests rely on English labels, and Playwright sends `Accept-Language: en-US` by default. The owner changes the default through env.
- **Tests.** PHPUnit: locale detection (user → cookie → Accept-Language → default, invalid value), `POST /locale` (guest/authenticated/invalid), the shared `locale` prop, key parity of `en.json`/`uk.json`, `auth.failed` in Ukrainian. Playwright `e2e/admin-i18n.spec.js`: switching on the login page and in the admin, persistence after logout; a test in `afterEach` returns `en` for the seeded user so as not to break `admin-shell.spec.js`.

## Risks / Trade-offs

- [Editing an English text breaks the key and its translation] → the parity test catches a `en`/`uk` mismatch; keys are changed together with both files.
- [All translations go in every response] → while there are few keys this is acceptable; an item for the future — splitting by domain.
- [Forgetting to wrap a string in `t()`] → not caught automatically; the rule is recorded in `docs/admin-i18n.md` and checked during apply (grep for literals in JSX).
- [E2E depend on the `users.locale` state of the seeded user] → the language is restored in `afterEach`, explained in `docs/e2e-testing.md`.
- [The `locale` cookie is encrypted and not readable by client JS] → this is expected: the server provides the language in a prop, JS does not read the cookie.
- [Cache-Control/CDN] → the admin is behind authentication, there is no public caching; for the public site this is a separate topic.

## Migration Plan

1. `php artisan migrate` adds `users.locale` (nullable) — existing users are not changed; detection by cookie/Accept-Language applies.
2. Rollback: `migrate:rollback` removes the column; the middleware is not used without the column, so the rollback of code changes and of the migration goes together.
