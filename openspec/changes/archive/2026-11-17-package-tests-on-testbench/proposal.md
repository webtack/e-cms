# Proposal

## Why

The PHPUnit tests (about two hundred files) exercise the CMS but run inside the demo application (`Tests\TestCase`, `base_path()`, `App\Models\User`, the demo's `public/` and `themes/`). Once the code lives in the package, the package needs its own suite that runs without any host application, otherwise nothing proves that the package works on its own. This is step 5 of 7 (series in `package-scaffold-and-namespace`).

## What Changes

- Move the Feature and Unit tests to the package (`tests/`, namespace `Webtack\Ecms\Tests\`) and run them with `orchestra/testbench` and PHPUnit 12.
- Replace the demo `Tests\TestCase` with a package `TestCase` extending Testbench's: registers `EcmsServiceProvider`, configures the user model (a fixture `User` that uses `HasEcmsAccess`), the in-memory SQLite database, Laravel's default `users` migration (the host table the package extends) and the package migrations.
- Adapt the tests that depended on the demo layout: paths to `themes/default` and `tests/Fixtures` (package paths), `public_path('themes/default/manifest.json')` (built theme assets are published into Testbench's public directory in setup), the `ReservedPathsGuardTest` (checks that reserved paths cover the directories a Laravel public folder and the package publish targets contain), `MediaMigrationTest` (package migration path).
- Leave out the Legacy tests (`tests/Feature/Legacy`): they follow the legacy import code to the demo in step 7.
- Add new tests for package-specific behaviour: auto-registration, middleware group, route order (the catch-all last, also after `route:cache`), `public_site`/`auth_routes` switches, theme roots (application over package), translation override, roleless user denied, installer idempotency and user-model preparation, factory binding for every model, Inertia version from the package manifest, missing-assets message.
- Add CI (GitHub Actions) for the package: PHPUnit, Pint, `check:dist`.
- Add `phpunit.xml` and a Composer `test` script in the package.

## Capabilities

### New Capabilities

- `package-testing`: how the package is verified on its own — host-independent suite, the fixtures it provides, what it must cover and what runs in CI.

### Modified Capabilities

<!-- None: runtime behaviour does not change. The browser e2e suite is untouched here (it stays with the demo). -->

## Impact

- New in the package: `tests/` (moved and adapted), `tests/Fixtures/`, `phpunit.xml`, `.github/workflows/tests.yml`, `composer.json` (`require-dev`: `orchestra/testbench`, `phpunit/phpunit`, `mockery/mockery`, `laravel/pint`, `fakerphp/faker`).
- Changed: `UserFactory` (builds the configured user model), tests that use `base_path`/`public_path`.
- Docs (package): `docs/package-testing.md` (new), `docs/README.md`; the "PHP tests" rules for the package are described there (the demo keeps its `CLAUDE.md` rules until step 7).
