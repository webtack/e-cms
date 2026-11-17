# Design

## Context

Observed in the demo: about two hundred test files in Feature and Unit folders plus the Legacy ones (the counts change with every feature; measure them with `find tests -name '*.php' | wc -l`), and hundreds of references to `App\Models\User`/`User::factory` in tests. `tests/TestCase.php` extends the Laravel TestCase and provides `authorId()` via `User::factory()`. `phpunit.xml` runs in-memory SQLite with the env values the app relies on. Path-dependent tests: `ThemeManagerTest` (`base_path('themes/default/views')`), `DefaultFieldTemplatesTest` (`base_path('themes/default/views/fields/…')`), `DefaultThemeStylingTest`/`ThemeManagerTest`/`ThemeAssetTest` (`public_path('themes/default/manifest.json')`, `public_path('app/…')`), `ReservedPathsGuardTest` (scans `public_path()` directories), `MediaMigrationTest` (`database_path('migrations/…')`), `FileStorageTest` (`base_path('tests/Fixtures/…')`), `UsesFixtureThemes` (temp theme dirs under `storage_path`), `TranslationParityTest` (`config('app.available_locales')` becomes `ecms.locales`; `lang_path()` becomes the package `lang/` directory) and `PublicLanguageRoutingTest` (assumes the host has `lang/uk.json` and no `lang/pl.json`; it must use the package files through the translations helper of step 2). Tests that read the route table (for example `Access/RouteCoverageTest`) are not yet checked against the package's route files and are triaged when the tests are moved. See proposal.md for motivation.

## Goals / Non-Goals

**Goals:** the non-legacy tests of the demo run in the package against Testbench; new tests lock in package guarantees; CI.

**Non-Goals:** rewriting test logic; moving the Playwright e2e suite; testing the legacy import (it lives in the demo).

## Decisions

**Testbench + PHPUnit 12.** `orchestra/testbench` (the major compatible with Laravel 13, resolved by Composer) gives an application skeleton with a `workbench` option. The package `TestCase` extends `Orchestra\Testbench\TestCase`, `getPackageProviders()` returns `EcmsServiceProvider`, `defineEnvironment()` sets `auth.providers.users.model` to the fixture user and the same `.env`-style values that `phpunit.xml` sets. Alternative: keep a nested fake Laravel app in `tests/` — rejected, Testbench is the standard and tracks the framework.

**Database.** `defineDatabaseMigrations()` calls `loadLaravelMigrations()` (host `users`) before the package migrations run (loaded by the provider), mirroring a real host. This also validates step 3 (extension of an existing users table, roleless users).

**Fixture user.** `Webtack\Ecms\Tests\Fixtures\User` (uses `HasEcmsAccess`, implements `EcmsUser`, `HasFactory` bound to the package `UserFactory`). `UserFactory` builds the model returned by the user resolver, so it also serves hosts. Tests replace `App\Models\User` with the fixture by the same mechanical rename script as step 1 (a second mapping pass).

**Paths.** A helper trait/`TestCase` method `packagePath($relative)` replaces `base_path()` for package files; `database_path('migrations/…')` becomes the package migrations path. `public_path('themes/default/manifest.json')` is satisfied by copying `dist/themes/default` into Testbench's public directory in `setUp()` (published like a host would; requires the committed `dist/` from step 4). `ReservedPathsGuardTest` is rewritten to assert that `PublicPaths::reserved()` contains `build`, `storage`, `themes`, `vendor` and `app` (the real directories of a Laravel public folder, the directory the theme static files are published to and the package publish target) and every entry of `content.reserved_paths` instead of scanning the demo's `public/`.

**Excluded tests.** `tests/Feature/Legacy` moves to the demo in step 7 with the legacy code; the migration test for `legacy_import_map` goes with it.

**New tests (package-only).** Registered as `tests/Feature/Package/*`: provider registration, middleware group, route order with and without `route:cache`, switches, theme roots, translation override, roleless denial, Inertia version, missing assets message, factory resolution, installer (first run, repeat, customised user model fixture, UUID user precondition). Added by the decisions of steps 2 to 4: the language prefix (`ResolvePublicLanguage` registered only with `public_site`, no database access before the package migrations, a language code that is the first segment of a host route is refused), `ecms.locales` in a host without `app.available_locales`, the translation override rule (host JSON wins; a key the package defines in `auth.php`/`validation.php` wins), `ThemeTemplateCopier` without a `themes/` directory, a roleless user as the target of `UserPolicy`, the avatar migration, and the publish tags `ecms-theme` (full tree, `--force` refresh), `ecms-theme-tooling` and the conditional static-files entry of `ecms-assets`. These map one-to-one to the scenarios in the specs of steps 2–4 and to `package-testing`.

**Run policy.** The project rule "run only the changed area's tests" continues in the package: the package `docs/package-testing.md` documents `--filter`/path usage and that a full run is for migrations, `TestCase`, `phpunit.xml`, dependencies, middleware, providers, config and authentication changes.

**CI.** `.github/workflows/tests.yml`: matrix PHP 8.3 and 8.4 (`prefer-stable`, plus one `prefer-lowest` job if the lowest set resolves), steps `composer install`, `vendor/bin/phpunit`, `vendor/bin/pint --test`, Node 22 `npm ci`, `npm run check:dist`. Alternative: no CI — rejected, `dist/` freshness needs automated enforcement.

Docs: new `docs/package-testing.md`; tests are described together with each area's doc (unchanged); the e2e doc stays in the demo.

## Risks / Trade-offs

- [Some tests relied on the demo `public/` or built files and fail for non-logic reasons] → The path adaptation list above is complete for what was found by `grep`; remaining failures are triaged individually (each failure is fixed in the test setup, not by weakening assertions).
- [Testbench's skeleton differs from the demo's stack (middleware, session, config)] → `defineEnvironment()` sets the same values as `phpunit.xml`; differences found are documented in `docs/package-testing.md`.
- [the user references in tests renamed mechanically may hide a wrong replacement] → The rename is verified by running the full suite (it changes `TestCase`, providers, config: a full run is justified here).
- [`prefer-lowest` fails because of transitive constraints] → Start with `prefer-stable` only; add lowest later if desired.

## Migration Plan

Run the suite in the package; once it is green, the demo's own `tests/` (except Legacy) are removed in step 7, together with `app/`.

## Open Questions

- Whether to enforce a minimum coverage percentage in CI. Does not affect specs or tasks.

### Audit 2026-11-17 (series deferred), resolved

- **Counts.** They change with every feature and are not recorded here. New folders such as `Feature/Dashboard` and more files in `Admin`, `Content`, `Public` and `Theme` appeared since planning, so the path-dependent list above must be re-collected with `grep` right before the move (task 2.2).
- **Global middleware.** Resolved in `package-service-provider-and-routing` (task 2.3): `ResolvePublicLanguage` is registered by the provider only when `public_site` is on. The language-prefix tests follow that decision (see "New tests").

#### Pre-start audit (2026-11-17, task 0.1)

Baseline commit: `45d2095`. `git diff --stat 124812e..45d2095` outside `openspec/` is empty: no code, config, route, middleware or docs changed since the security-hardening addendum, so the facts and counts of this series stay valid (532 files reference `App\` in `app/`, `config/`, `database/`, `routes/`, `themes/`, `resources/`, `tests/` and `bootstrap/` (564 with `docs/`); the next audit starts from this commit).
