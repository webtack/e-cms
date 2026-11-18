# Tasks

## 1. Language configuration and storage of the choice

- [x] 1.1 Add `available_locales` (`en` → English, `uk` → Українська) to `config/app.php`; check: `docker compose exec app php artisan tinker --execute="dump(config('app.available_locales'));"` returns both languages
- [x] 1.2 Create the migration `add_locale_to_users_table` (`locale` string(5) nullable) and run it; check: `docker compose exec app php artisan migrate` passes, and `migrate:rollback` and a repeated `migrate` pass too
- [x] 1.3 Update `App\Models\User` (the `locale` field is not mass-assignable, writable through the controller) with PHPDoc; check: `docker compose exec app vendor/bin/pint --dirty --format agent` without remarks
- [x] 1.4 Create `docs/admin-i18n.md` (available languages, detection priority, `users.locale`, cookie) and add a link in `docs/README.md`; check: all links in the index open

## 2. Server-side detection and switching of the language

- [x] 2.1 Create the middleware `App\Http\Middleware\SetLocale` (user → cookie → `Accept-Language` → `APP_LOCALE`, ignore invalid values) and register it in the `web` group before `HandleInertiaRequests` in `bootstrap/app.php`; check: the PHPUnit tests of priority and `uk-UA` → `uk` pass
- [x] 2.2 Create `LocaleController` + `POST /locale` (`locale.update`, `throttle:30,1`, validation `Rule::in`, saving to `users.locale` for an authenticated user and the cookie `locale` for everyone, `back()`); check: feature tests for a guest, an authenticated user and an invalid value
- [x] 2.3 Add the shared prop `locale` (`current`, `available`, `messages`) in `HandleInertiaRequests::share` and the type in `resources/js/types/index.d.ts`; check: an `assertInertia` test of the prop structure, `<html lang>` matches the current language
- [x] 2.4 Add JSDoc/PHPDoc in English to the public classes and methods with `@example` where usage is not obvious; update `docs/admin-i18n.md` (middleware, `POST /locale`, the shared prop); check: the description matches `docker compose exec app php artisan route:list --name=locale`

## 3. Server translations

- [x] 3.1 Publish the English files: `docker compose exec app php artisan lang:publish`; keep only the needed ones (`auth.php`, `validation.php`; do not add `passwords.php`/`pagination.php` if not needed); check: `__('auth.failed')` returns the English text
- [x] 3.2 Create `lang/uk/auth.php` (full translation of `failed`, `password`, `throttle`) and `lang/uk/validation.php` (the rules in use + `attributes` for `email`, `password`); check: a feature test — a wrong password for a user with `uk` returns the Ukrainian message in `errors.email`
- [x] 3.3 Update `docs/admin-i18n.md` (where the server translations live, how to add a language); check: the steps from the document can be reproduced

## 4. Client translation mechanism

- [x] 4.1 Create `lang/en.json` and `lang/uk.json` (the same set of keys) and a PHPUnit key-parity test; check: the test passes, and removing a key from `uk.json` makes it red
- [x] 4.2 Create `resources/js/lib/i18n.ts` (`translate(messages, key, replace?)`: fallback to the key, placeholders `:name`, longer names first) with JSDoc and `@example`; check: `npm run build` without TypeScript errors, the behaviour is verified in e2e (task 7.2)
- [x] 4.3 Create `resources/js/hooks/useTranslation.ts` (`{ t, locale, locales }` from `usePage().props.locale`) with JSDoc and `@example`; check: `npm run build` passes
- [x] 4.4 Update `docs/admin-i18n.md` (how to use `t()`, placeholders, the rule "every visible string through `t()`", keys = English phrases); check: the example from the document compiles in `npm run build`

## 5. Language switcher and translation of the interface

- [x] 5.1 Create `Components/Admin/LocaleSwitcher.tsx` (DropdownMenu, native language names, a mark for the current one, `router.post('/locale', …, { preserveScroll: true, preserveState: true })`) with JSDoc; add it to `AdminHeader` and to `Pages/Auth/Login.tsx`; check: switching works manually on `http://localhost:8080/login` and in the admin, the sidebar state is kept
- [x] 5.2 Move `navigation.ts` to keys and apply `t()` in `AppSidebar`, `Breadcrumbs` (including the title "Admin"); check: `getBreadcrumbs` remains a pure function, `npm run build` passes
- [x] 5.3 Apply `t()` in `Login`, `VerifyEmail`, `Dashboard`, `Profile`, `UserMenu`, `ThemeToggle`, `AdminHeader` (including `aria-label` and `<Head title>`) and add all keys to `lang/en.json` and `lang/uk.json`; check: `grep` over the JSX finds no English literals outside `t()`, the parity test passes
- [x] 5.4 Update `docs/admin-shell.md` (navigation as translation keys, the switcher in the header) and `docs/admin-auth.md` (the switcher on the login page); check: the described components exist in `resources/js/`

## 6. Automated PHP tests

- [x] 6.1 Add `tests/Feature/Locale/SetLocaleTest.php`, `LocaleControllerTest.php`, `SharedLocalePropsTest.php` for the scenarios of the `admin-i18n` specification; check: `docker compose exec app php artisan test --compact` is fully green
- [x] 6.2 Run Pint: `docker compose exec app vendor/bin/pint --dirty --format agent`; check: no changes after the run

## 7. E2E and final check

- [x] 7.1 Make sure that the existing `e2e/admin-shell.spec.js` and `smoke.spec.js` remain green (the default language `en`); check: `npm run test:e2e`
- [x] 7.2 Add `e2e/admin-i18n.spec.js`: switching on the login page, switching in the admin with the sidebar state kept, the language kept after logout, `afterEach` returns `en` for the seeded user; check: `npm run test:e2e` is green twice in a row
- [x] 7.3 Update `docs/e2e-testing.md` (isolation of the `users.locale` state, why the test returns `en`); check: the description matches the spec
- [x] 7.4 Build the frontend with `npm run build` and go through all screens in both languages; check: no build errors, no errors in the browser console
