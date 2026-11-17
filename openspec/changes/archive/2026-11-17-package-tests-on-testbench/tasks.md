# Tasks

## 1. Harness

- [x] 1.1 Add `orchestra/testbench`, `phpunit/phpunit`, `mockery/mockery`, `laravel/pint`, `fakerphp/faker` to the package `require-dev`, `phpunit.xml` (suites Unit/Feature, same env as the demo) and a Composer `test` script; install inside the container: `docker compose exec -w /var/www/e-cms-package app composer install`. Verify: `vendor/bin/phpunit --list-tests` runs (empty suite is fine).
- [x] 1.2 Create `tests/TestCase.php` (Testbench, provider, user model config, default `users` migration, helpers `packagePath()` and `authorId()`), `tests/Fixtures/User.php` and make `UserFactory` build the configured user model. Verify with one smoke test that creates a user with a role through the factory.
- [x] 1.3 Write `docs/package-testing.md` (how to run, run policy, Testbench setup, fixtures) and add it to `docs/README.md`; verify links.

## 2. Move and adapt tests

- [x] 2.1 Copy `tests/Unit` and `tests/Feature` (excluding `Legacy`) with `tests/Fixtures/word97.doc`, apply the namespace mapping (`Tests\` → `Webtack\Ecms\Tests\`, `App\` → `Webtack\Ecms\`, `App\Models\User` → fixture user). Verify: no `App\` remains in `tests/` and `php -l` passes.
- [x] 2.2 Adapt path-dependent tests (`ThemeManagerTest`, `DefaultFieldTemplatesTest`, `DefaultThemeStylingTest`, `ThemeAssetTest`, `ReservedPathsGuardTest`, `MediaMigrationTest`, `FileStorageTest`, `UsesFixtureThemes`, `TranslationParityTest`, `PublicLanguageRoutingTest`) as described in design.md, publishing `dist/themes/default` in setup. Verify each of these test files passes: `docker compose exec -w /var/www/e-cms-package app vendor/bin/phpunit tests/Feature/Theme`, then the other files by path.
- [x] 2.3 Run the full package suite (justified: `TestCase`, providers, config and authentication changed) and fix failures in test setup, not assertions. Verify: all moved tests pass; report the number of moved tests versus the number of non-legacy demo tests (counted with `find tests -name '*.php'` in each).

## 3. Package-specific tests

- [x] 3.1 Add `tests/Feature/Package/` tests for registration, middleware group, redirects, 404 renderer, `public_site` and `auth_routes` switches, route order (also after `route:cache`), theme roots, translation override. Verify with `--filter=Package`.
- [x] 3.2 Add tests for roleless denial (a user with `role_id = null`: `none` on every scope, `false` on every system permission, `canEditAnyContent()` false, `forClient()` does not throw, the admin users list renders with a "No role" label), migrations preserving existing users, Inertia version from the package manifest, missing-assets message, factory resolution for every model. Verify with `--filter=Package`.
- [x] 3.3 Add installer tests (first run, repeat run, stock and customised user model, no credentials without interaction in any environment, UUID precondition). Verify with `--filter=Install`.
- [x] 3.4 Add tests for the decisions of steps 2 to 4 under `tests/Feature/Package/`: the language prefix (`ResolvePublicLanguage` only with `public_site`; a `GET /api/x` before the package migrations is answered without a database error; adding a language whose code is the first segment of a host route is refused); `ecms.locales` (a host without `app.available_locales` serves a web request and accepts `POST /locale` with `uk`; the public site takes the locale `en` from the package files when the host has none); the translation rule (host JSON key wins, a key defined by the package in `auth.php` wins over the host's); `ThemeTemplateCopier` without a `themes/` directory and with an application theme; a roleless user as the target of `UserPolicy` (open, edit, delete, assign a role); the avatar migration keeping rows; the publish tags `ecms-theme` (complete tree, `--force` restores an edited file), `ecms-theme-tooling` and the conditional `public/app/default` entry of `ecms-assets` (publishing succeeds with and without static files). Verify with `--filter=Package`.

## 4. CI

- [x] 4.1 Add `.github/workflows/tests.yml` (PHP 8.3/8.4 matrix, PHPUnit, `pint --test`, Node `npm ci` + `check:dist`). Verify by a YAML lint (`python3 -c "import yaml,sys; yaml.safe_load(open(sys.argv[1]))"`) and by running each listed command locally once.
- [x] 4.2 Run Pint on the package tests (`vendor/bin/pint --test`) and verify no style violations remain.
