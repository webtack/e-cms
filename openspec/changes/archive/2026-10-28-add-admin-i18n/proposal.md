# Proposal

## Why

The whole admin interface is currently hard-coded in English (the menu in `navigation.ts`, pages, aria labels, validation messages without `lang/`). The more pages are added, the more expensive it becomes to move strings into translations later. The multilingual mechanism has to be laid down now, before content types, the page builder and the media library appear: then every new screen is written through translations from the start.

## What Changes

- Add an admin interface language mechanism with two languages at the start: Ukrainian (`uk`) and English (`en`); the list of available languages is set in the configuration, so a language can be added without code changes.
- Detect the language of a request: the saved choice of an authenticated user → the choice of a guest (cookie) → `Accept-Language` → `APP_LOCALE`; ignore unknown values.
- Add the field `users.locale` (nullable) and `POST /locale` to change the language: for an authenticated user it is stored on the user, for a guest — only in a cookie.
- Pass the current language, the list of languages and the translations to React through shared Inertia props; add the `useTranslation()` hook with the function `t(key, replace?)` (source — `lang/{locale}.json`, keys — English phrases, `:name` placeholders as in Laravel).
- Translate the existing interface: the login page, verify-email, Dashboard, Profile, the menu, breadcrumbs, the header, the user menu, the theme switcher, aria labels, `<Head>` titles; server messages (`auth.failed`, `auth.throttle`, validation) — in files `lang/uk/*.php`.
- Add a language switcher: in the admin header (for authenticated users) and on the login page (for guests).
- Update the e2e and PHPUnit tests, and the documentation.
- Not included: translation of content (pages/entries), localized URLs (`/uk/admin`), date/number formatting, plurals, RTL, SSR.

## Capabilities

### New Capabilities
- `admin-i18n`: detection, storage and switching of the admin interface language; delivery of translations to React; localization of server messages.

### Modified Capabilities
- `admin-shell`: the names of the sidebar navigation items are displayed in the current interface language.

## Impact

- Code: `config/app.php` (`available_locales`), a new migration for `users.locale`, `App\Models\User`, a new middleware `SetLocale` and `HandleInertiaRequests` (shared `locale` prop), a new `LocaleController` + a route in `routes/web.php`, `bootstrap/app.php`; `lang/en.json`, `lang/uk.json`, `lang/uk/{auth,validation}.php`; frontend: `resources/js/lib/i18n.ts`, `resources/js/hooks/useTranslation.ts`, `Components/Admin/LocaleSwitcher.tsx`, edits to the existing components and pages, `types/index.d.ts`.
- Tests: PHPUnit `tests/Feature/Locale/*`, a check that `en.json`/`uk.json` have the same set of keys; Playwright `e2e/admin-i18n.spec.js`.
- Dependencies: no new npm/composer packages.
- Documentation: create `docs/admin-i18n.md`; update `docs/admin-shell.md` (navigation as translation keys), `docs/admin-auth.md` (the switcher on the login page), `docs/e2e-testing.md` (isolation of language states), `docs/README.md` (index).
